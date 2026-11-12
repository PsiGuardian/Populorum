#!/usr/bin/env python3
"""Offline official-BOAMP documentary cross-reference, without editing national DECP.

Automatic concordance applies to a BOAMP *procedure notice*, not to a
specific awarded lot, financial commitment, invoice, or paid amount.

Usage (from apps/symfony):
  python3 tools/reconcile_procurement_boamp.py --contract-key <40 hex chars>
  python3 tools/reconcile_procurement_boamp.py --limit 20 --pause 1.0

Writes only data/research/procurement-auto-concordance.sqlite. Requests must
never run inside an HTTP page request. No external Python dependencies.
"""
from __future__ import annotations
import argparse
from datetime import datetime, timezone
from decimal import Decimal, InvalidOperation
import hashlib
import json
from pathlib import Path
import re
import sqlite3
import time
import unicodedata
from urllib.parse import urlencode, urlsplit
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[1]
NATIONAL = ROOT / 'data/research/public-procurement.sqlite'
SIDECAR = ROOT / 'data/research/procurement-auto-concordance.sqlite'
API_HOST = 'boamp-datadila.opendatasoft.com'
API = 'https://' + API_HOST + '/api/explore/v2.1/catalog/datasets/boamp/records'
SCHEMA_VERSION = 'populorum-boamp-concordance-1'
SCHEMA = """
CREATE TABLE IF NOT EXISTS metadata(key TEXT PRIMARY KEY,value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS checks(
 contract_key TEXT PRIMARY KEY,checked_at TEXT NOT NULL,
 status TEXT NOT NULL CHECK(status IN ('ok','failed')), result_count INTEGER NOT NULL,
 message TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS reference_matches(
 contract_key TEXT NOT NULL,idweb TEXT NOT NULL,
 status TEXT NOT NULL CHECK(status IN ('procedure_ids','candidate')),
 basis TEXT NOT NULL,
 title TEXT NOT NULL,url TEXT NOT NULL,
 publication_date TEXT NOT NULL, notice_kind TEXT NOT NULL,
 notice_sha256 TEXT NOT NULL, notice_total_eur TEXT NOT NULL,
 first_seen_at TEXT NOT NULL,last_seen_at TEXT NOT NULL,
 PRIMARY KEY(contract_key,idweb)
);
CREATE INDEX IF NOT EXISTS concordance_contract ON reference_matches(contract_key,status,last_seen_at DESC);
"""
STOP = {'pour','avec','dans','des','les','une','sur','aux','travaux',
        'marche','fourniture','services','prestation','prestations','lot',
        'public','publics','renovation','installation','contrat','consultation'}


def terms(value: str) -> list[str]:
    normalized = unicodedata.normalize('NFKD',value.casefold())
    normalized = ''.join(c for c in normalized if not unicodedata.combining(c))
    return [x for x in re.findall(r'[a-z0-9]{4,}',normalized)
            if x not in STOP and not x.isdigit()]


def title_terms(value: str) -> set[str]:
    """Light French singular/plural comparison for *titles only*.

    A BOAMP notice may say 'internat' where DECP says 'internats'.
    The official-API search terms remain untouched. This normalization
    never supplies documentary proof of a specific procedure or lot.
    """
    normalized=set()
    for word in terms(value):
        # A deliberately narrow rule: avoid 'plus', 'cours', 'statuts',
        # etc. and do not attempt semantic synonyms or regional demonyms.
        if len(word)>=6 and word.endswith('s') and not word.endswith(('ss','us','is')):
            word=word[:-1]
        normalized.add(word)
    return normalized


def exact_token(needle: str, haystack: str) -> bool:
    if not needle:
        return False
    return re.search(r'(?<![a-zA-Z0-9])'+re.escape(needle)+r'(?![a-zA-Z0-9])',
                     haystack, flags=re.IGNORECASE) is not None


def unique_notice_total(payload: object) -> str:
    """Conservative whole-notice amount from a uniquely defined EUR TotalAmount.

    This is NEVER an amount certified for one lot or a payment. In
    ambiguous multi-amount notices return nothing, not a guess.
    """
    totals=set()
    def walk(obj: object, depth: int = 0) -> None:
        if depth > 32 or len(totals) > 2:
            return
        if isinstance(obj,dict):
            for k,v in obj.items():
                if k in ('cbc:TotalAmount','TotalAmount') and isinstance(v,dict):
                    if v.get('@currencyID') == 'EUR':
                        raw=v.get('#text')
                        if isinstance(raw,str) and re.fullmatch(r'[0-9]{1,14}(?:\.[0-9]{1,2})?',raw):
                            try:totals.add(str(Decimal(raw).quantize(Decimal('0.01'))))
                            except InvalidOperation:pass
                else:walk(v,depth+1)
        elif isinstance(obj,list):
            for v in obj[:300]:walk(v,depth+1)
    walk(payload)
    return next(iter(totals)) if len(totals)==1 else ''


def classify(contract: dict, notice: dict) -> dict | None:
    """Return source-linked association, never a payment or a lot-level award.

    Claims procedure IDs only when both SIRET and full procedure reference
    occur in the official notice body. Partial title similarity is *never*
    enough to establish a link on its own.
    """
    buyer = str(contract.get('buyer_id') or '')
    reference = str(contract.get('original_id') or '').strip()
    if not re.fullmatch(r'[0-9]{14}',buyer):
        return None
    if len(reference) < 6:
        return None
    idweb = notice.get('idweb')
    url = notice.get('url_avis')
    if not isinstance(idweb,str) or not re.fullmatch(r'[0-9]{2}-[0-9]{3,8}',idweb):
        return None
    if not isinstance(url,str):
        return None
    parsed = urlsplit(url)
    if parsed.scheme != 'https' or parsed.hostname not in ('www.boamp.fr','boamp.fr'):
        return None
    if parsed.username or parsed.password or len(url) > 2048:
        return None
    # ODS uses DONNEES for the actual notice content; avoid matching text
    # from other returned records and never synthesize a provenance link.
    full = notice.get('donnees') or notice.get('DONNEES') or {}
    if isinstance(full,str):
        try:full=json.loads(full)
        except (ValueError,TypeError):return None
    if not isinstance(full,(dict,list)):
        return None
    raw = json.dumps(full,ensure_ascii=False,sort_keys=True,separators=(',',':'))
    buyer_found = exact_token(buyer,raw)
    reference_found = exact_token(reference,raw)
    title = str(notice.get('objet') or '')[:400]
    reference_title = str(contract.get('object') or '')
    name_tokens = title_terms(reference_title)
    title_tokens = title_terms(title)
    overlap = len(name_tokens & title_tokens) / max(1,len(name_tokens | title_tokens))
    # Keep stricter original-word scoring to establish a distinctive
    # numerical procedure reference: plural normalization may only help
    # produce a *candidate*, never upgrade it to verified procedure IDs.
    raw_name_tokens = set(terms(reference_title))
    raw_title_tokens = set(terms(title))
    raw_overlap = len(raw_name_tokens & raw_title_tokens) / max(
        1,len(raw_name_tokens | raw_title_tokens))
    # A numerical reference may coincide with an amount or date. Do not
    # certify it unless it is long and accompanied by a strong title match.
    distinctive_reference = (
        (any(c.isalpha() for c in reference) and any(c.isdigit() for c in reference))
        or (reference.isdigit() and len(reference) >= 10 and raw_overlap >= 0.50)
    )
    if buyer_found and reference_found and distinctive_reference:
        status = 'procedure_ids'
        basis = 'Référence distinctive et SIRET cités dans le contenu de l’avis BOAMP'
    elif buyer_found and overlap >= 0.35 and len(name_tokens & title_tokens) >= 3:
        # Similar wording is ONLY a lead. BOAMP's public procedure reference
        # often differs from the DECP identifier, and one buyer may publish
        # several near-identical lots.
        notice_years = re.findall(r'(?:19|20)[0-9]{2}', str(notice.get('dateparution') or ''))
        contract_year = str(contract.get('notice_date') or '')[:4]
        if notice_years and contract_year.isdigit() and abs(int(notice_years[-1]) - int(contract_year)) > 3:
            return None
        status = 'candidate'
        basis = 'SIRET cité dans l’avis et intitulé proche ; marché, année et lot à confirmer'
    else:
        return None
    pub = str(notice.get('dateparution') or '')[:40]
    kind = str(notice.get('nature_categorise_libelle') or notice.get('type_avis') or '')[:90]
    return {'contract_key':contract['contract_key'],'idweb':idweb,'status':status,
            'basis':basis,'title':title or 'Avis BOAMP ' + idweb,'url':url,
            'publication_date':pub,'notice_kind':kind,
            'notice_sha256':hashlib.sha256(raw.encode('utf-8')).hexdigest(),
            'notice_total_eur':unique_notice_total(full) if status == 'procedure_ids' else ''}


def search_expression(object_title: str, reference: str, buyer_id: str = '') -> str:
    """A focused ODSQL query; no generic national first-page search for buyers.

    Buyer-SIRET indexed in the official BOAMP "donnees" text, combined
    with a distinctive title term. If a SIRET is unavailable, a generic
    title query is NOT a trustworthy path to automatic concordance.
    """
    if not re.fullmatch(r'[0-9]{14}',buyer_id):
        raise ValueError('SIRET acheteur à 14 chiffres obligatoire pour la concordance')
    tokens = list(dict.fromkeys(terms(object_title)))
    # Prevent searches on a year or generic procurement vocabulary alone.
    words = [word for word in tokens if not word.isdigit() and len(word) >= 5]
    if not words:
        raise ValueError('Objet du marché insuffisamment distinctif')
    distinctive = max(words, key=lambda w: (len(w), -words.index(w)))
    return "search(donnees, '" + buyer_id + "') AND search(objet, '" + distinctive + "')"


def fetch_candidates(object_title: str, reference: str, buyer_id: str = '', timeout: float = 20.0) -> list[dict]:
    expression = search_expression(object_title, reference, buyer_id)
    params = urlencode({
        'where': expression,
        'select': 'idweb,objet,nomacheteur,dateparution,url_avis,nature_categorise_libelle,donnees',
        'limit': 30,
    })
    request = Request(API + '?' + params, headers={
        'User-Agent': 'Populorum-BOAMP-Concordance/1.1 (public documents)',
        'Accept': 'application/json',
    })
    with urlopen(request, timeout=timeout) as response:
        if urlsplit(response.url).hostname != API_HOST or urlsplit(response.url).scheme != 'https':
            raise ValueError('Redirection hors de l’API BOAMP officielle')
        body = response.read(5_000_001)
    if len(body) > 5_000_000:
        raise ValueError('Réponse BOAMP trop volumineuse')
    data = json.loads(body)
    if not isinstance(data, dict) or not isinstance(data.get('results'), list):
        raise ValueError('Schéma de réponse BOAMP inattendu')
    return [x for x in data['results'] if isinstance(x, dict)]


def store(sidecar: sqlite3.Connection, contract_key: str, matches: list[dict],
          status: str = 'ok', message: str = '') -> None:
    now=datetime.now(timezone.utc).isoformat(timespec='seconds')
    for match in matches:
        sidecar.execute("""INSERT INTO reference_matches(
          contract_key,idweb,status,basis,title,url,publication_date,notice_kind,
          notice_sha256,notice_total_eur,first_seen_at,last_seen_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
          ON CONFLICT(contract_key,idweb) DO UPDATE SET
          status=excluded.status,basis=excluded.basis,title=excluded.title,
          url=excluded.url,publication_date=excluded.publication_date,
          notice_kind=excluded.notice_kind,notice_sha256=excluded.notice_sha256,
          notice_total_eur=excluded.notice_total_eur,
          last_seen_at=excluded.last_seen_at""",
          (match['contract_key'],match['idweb'],match['status'],match['basis'],
           match['title'],match['url'],match['publication_date'],match['notice_kind'],
           match['notice_sha256'],match['notice_total_eur'],now,now))
    sidecar.execute("""INSERT INTO checks VALUES(?,?,?,?,?)
      ON CONFLICT(contract_key) DO UPDATE SET
      checked_at=excluded.checked_at,status=excluded.status,
      result_count=excluded.result_count,message=excluded.message""",
      (contract_key,now,status,len(matches),message[:500]))


def following_offset(start: int, processed: int, requested: int) -> int:
    """Progress bounded batches; start a new cycle at the end of the catalog."""
    return 0 if processed < requested else start + processed


def main() -> None:
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--contract-key',help='Clé d’une fiche DECP à contrôler')
    p.add_argument('--limit',type=int,default=10,help='Nombre de contrats interrogés (1 à 50)')
    p.add_argument('--offset',type=int,default=0,help='Position de reprise dans la liste chronologique des contrats')
    p.add_argument('--resume',action='store_true',help='Mémoriser automatiquement le prochain lot dans la base auxiliaire')
    p.add_argument('--pause',type=float,default=1.0,help='Délai entre requêtes (>=1 seconde)')
    p.add_argument('--decp',type=Path,default=NATIONAL)
    p.add_argument('--db',type=Path,default=SIDECAR)
    opts=p.parse_args()
    if opts.limit < 1 or opts.limit > 50 or opts.pause < 1 or opts.offset < 0 or opts.offset > 1000000:
        p.error('limit entre 1 et 50 ; pause >=1 seconde')
    if opts.contract_key is not None and not re.fullmatch(r'[a-f0-9]{40}',opts.contract_key):
        p.error('contract-key : 40 caractères hexadécimaux')
    if opts.resume and (opts.contract_key is not None or opts.offset != 0):
        p.error('--resume n’est pas compatible avec --contract-key ni --offset')
    if not opts.decp.is_file() or opts.db.resolve() == opts.decp.resolve():
        p.error('Index DECP obligatoire et base auxiliaire distincte')
    opts.db.parent.mkdir(parents=True,exist_ok=True)
    with sqlite3.connect('file:'+str(opts.decp.resolve())+'?mode=ro',uri=True) as decp:
        decp.row_factory=sqlite3.Row
        if decp.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()[0] != 'populorum-decp-2':
            p.error('Index national DECP v2 requis')
        with sqlite3.connect(opts.db,timeout=30) as sidecar:
            sidecar.executescript(SCHEMA)
            if sidecar.execute("SELECT value FROM metadata WHERE key='schema'").fetchone() not in (None,(SCHEMA_VERSION,)):
                p.error('Schéma du registre auxiliaire incompatible')
            sidecar.execute("INSERT OR IGNORE INTO metadata VALUES('schema',?)",(SCHEMA_VERSION,))
            saved=sidecar.execute("SELECT value FROM metadata WHERE key='next_offset'").fetchone()
            next_offset=int(saved[0]) if saved and saved[0].isdigit() else 0
            start_offset=next_offset if opts.resume else opts.offset
            if opts.contract_key:
                records=decp.execute("""SELECT contract_key,buyer_id,original_id,object FROM contracts
                                     WHERE contract_key=?""",(opts.contract_key,)).fetchall()
            else:
                # Stable key-based ordering for bounded, explicit batch resumes.
                # The caller can advance --offset between runs. Never scan the
                # entire national database from a web request.
                records=decp.execute("""SELECT contract_key,buyer_id,original_id,object
                  FROM contracts c WHERE c.kind='market' AND LENGTH(c.original_id)>=6
                    AND c.buyer_id GLOB '[0-9]*' AND LENGTH(c.buyer_id)=14
                  ORDER BY c.notice_date DESC,c.contract_key ASC LIMIT ? OFFSET ?""",
                  (opts.limit,start_offset)).fetchall()
            successes=0
            for index, record in enumerate(records):
                contract=dict(record)
                try:
                    notices=fetch_candidates(contract['object'],contract['original_id'],contract['buyer_id'])
                    matches=[m for n in notices if (m:=classify(contract,n)) is not None]
                    # No double counting when the API repeats the same BOAMP notice.
                    matches=list({m['idweb']:m for m in matches}.values())
                    with sidecar:store(sidecar,contract['contract_key'],matches)
                    successes+=1
                    print(f"{index+1}/{len(records)} {contract['contract_key']}: "
                          f"{len(notices)} avis examinés ; {len(matches)} avis lié(s), "
                          f"{sum(m['status']=='procedure_ids' for m in matches)} référence(s) concordante(s).",flush=True)
                except (OSError,ValueError,KeyError,json.JSONDecodeError) as exc:
                    with sidecar:store(sidecar,contract['contract_key'],[],status='failed',message=str(exc))
                    print(f"{contract['contract_key']}: recherche indisponible ({exc})",flush=True)
                if index+1 < len(records):time.sleep(opts.pause)
            if opts.resume:
                # Progress even when a source API request fails; its failure
                # remains stored in checks and can be retried explicitly.
                new_offset = following_offset(start_offset,len(records),opts.limit)
                with sidecar:
                    sidecar.execute("""INSERT INTO metadata(key,value) VALUES('next_offset',?)
                      ON CONFLICT(key) DO UPDATE SET value=excluded.value""",
                      (str(new_offset),))
                print(f"Position de reprise automatique : {new_offset}",flush=True)
            print(f"{successes}/{len(records)} rapprochements API effectués. "
                  "Aucun montant payé déduit ; registre DECP intact.")


if __name__ == '__main__':
    main()
