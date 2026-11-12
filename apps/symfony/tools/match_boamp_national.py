#!/usr/bin/env python3
"""Reconcile official BOAMP local archive with DECP locally, without HTTP calls.

From apps/symfony:
 python3 tools/match_boamp_national.py --limit 100 --offset 0
 python3 tools/match_boamp_national.py --resume --limit 2000
 python3 tools/match_boamp_national.py --contract-key <40-hex>

Reads:  public-procurement.sqlite + boamp-national.sqlite (read-only)
Writes: procurement-auto-concordance.sqlite ONLY (separate evidence file).
Stores versions of match evidence, never financial payments or merged lots.
"""
from __future__ import annotations
import argparse
from collections import Counter,OrderedDict
from datetime import datetime,timezone
import hashlib
import json
from pathlib import Path
import re
import sqlite3
import zlib
from import_boamp_national import DEFAULT_DB as BOAMP,SCHEMA_VERSION as BOAMP_SCHEMA,EXPORT
from reconcile_procurement_boamp import (NATIONAL, SIDECAR, SCHEMA as OLD_SCHEMA,
                                        SCHEMA_VERSION as OLD_VERSION,
                                        classify,terms,title_terms,geographic_text,ROOT)

LOCAL_SCHEMA="""
CREATE TABLE IF NOT EXISTS local_checks(
 contract_key TEXT PRIMARY KEY,checked_at TEXT NOT NULL,
 scanned_notices INTEGER NOT NULL,matched_notices INTEGER NOT NULL,
 truncated INTEGER NOT NULL,source_revision TEXT NOT NULL,
 status TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS local_matches(
 contract_key TEXT NOT NULL,idweb TEXT NOT NULL,notice_sha TEXT NOT NULL,
 status TEXT NOT NULL,basis TEXT NOT NULL,
 title TEXT NOT NULL,url TEXT NOT NULL,publication_date TEXT NOT NULL,
 notice_kind TEXT NOT NULL,first_seen_at TEXT NOT NULL,
 last_seen_at TEXT NOT NULL,active INTEGER NOT NULL CHECK(active IN (0,1)),
 PRIMARY KEY(contract_key,idweb,notice_sha));
CREATE INDEX IF NOT EXISTS local_matches_contract ON local_matches(contract_key,active,status);
-- Append-only observations: previous decisions and unobserved matches are
-- never overwritten. Legacy rows are explicitly marked as unverifiable v0.
CREATE TABLE IF NOT EXISTS local_decision_history(
 event_id INTEGER PRIMARY KEY AUTOINCREMENT,
 contract_key TEXT NOT NULL,idweb TEXT NOT NULL,notice_sha TEXT NOT NULL,
 algorithm_version TEXT NOT NULL,algorithm_sha256 TEXT NOT NULL,
 contract_sha256 TEXT NOT NULL,event_type TEXT NOT NULL,
 classification TEXT NOT NULL,basis TEXT NOT NULL,
 factors_json TEXT NOT NULL,source_revision TEXT NOT NULL,
 corpus_status TEXT NOT NULL,recorded_at TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS local_history_contract
 ON local_decision_history(contract_key,event_id DESC);
CREATE INDEX IF NOT EXISTS local_history_notice
 ON local_decision_history(contract_key,idweb,event_id DESC);
-- Scan snapshots also cover contracts with zero documentary candidates.
-- Previous local_checks entries are NOT retroactively given an algorithm version.
CREATE TABLE IF NOT EXISTS local_scan_history(
 scan_id INTEGER PRIMARY KEY AUTOINCREMENT,
 contract_key TEXT NOT NULL,
 algorithm_version TEXT NOT NULL,
 algorithm_sha256 TEXT NOT NULL,
 contract_sha256 TEXT NOT NULL,
 source_revision TEXT NOT NULL,
 corpus_status TEXT NOT NULL,
 scanned_notices INTEGER NOT NULL,
 matched_notices INTEGER NOT NULL,
 truncated INTEGER NOT NULL,
 match_signature TEXT NOT NULL,
 result TEXT NOT NULL,
 recorded_at TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS local_scan_history_contract
 ON local_scan_history(contract_key,scan_id DESC);
"""
ALGORITHM_VERSION='boamp-local-v1.1-decision-history'
MAX_LOCAL=4000
GEOGRAPHY=ROOT / 'data/geography/communes-2026.sqlite'


def short_unique_communes(path:Path)->dict[str,str]:
    """Only exact, unique official names lost by generic title tokenization.

    This is a conservative candidate-signal catalog, NEVER a set of
    verified geographic identifiers from BOAMP. The COG is read only.
    """
    if not path.is_file():
        return {}
    with sqlite3.connect('file:'+str(path.resolve())+'?mode=ro',uri=True) as db:
        vintage=db.execute(
            "SELECT value FROM metadata WHERE key='vintage'").fetchone()
        if not vintage or vintage[0]!='2026':
            raise ValueError('Référentiel INSEE attendu : COG 2026')
        rows=db.execute('SELECT code,name FROM communes').fetchall()
    if not rows:
        raise ValueError('COG 2026 vide')
    labels=Counter(geographic_text(str(name)) for _,name in rows)
    return {
        str(code).upper():str(name) for code,name in rows
        if labels[geographic_text(str(name))]==1
        and not title_terms(str(name))
        and re.fullmatch(r'(?:[0-9]{5}|2[AB][0-9]{3})',str(code).upper())
    }


def algorithm_sha256()->str:
    """SHA-256 of both actual rule and runner sources (not a guessed version)."""
    digest=hashlib.sha256()
    for name in ('reconcile_procurement_boamp.py','match_boamp_national.py'):
        payload=(ROOT/'tools'/name).read_bytes()
        digest.update(name.encode('utf-8')+b'\0')
        digest.update(payload)
    return digest.hexdigest()


def contract_sha256(contract:dict)->str:
    fields=('contract_key','buyer_id','original_id','object','notice_date',
            'cpv','kind','location_type','location_code')
    payload={field:contract.get(field) for field in fields}
    return hashlib.sha256(json.dumps(payload,ensure_ascii=False,
        sort_keys=True,separators=(',',':')).encode('utf-8')).hexdigest()


def append_history(sidecar:sqlite3.Connection,contract_key:str,
                   notice_id:str,notice_sha:str,version:str,
                   algorithm_hash:str,contract_hash:str,event_type:str,
                   classification:str,basis:str,factors:dict,
                   source_revision:str,coverage:str,now:str)->None:
    sidecar.execute("""INSERT INTO local_decision_history
     (contract_key,idweb,notice_sha,algorithm_version,algorithm_sha256,
      contract_sha256,event_type,classification,basis,factors_json,
      source_revision,corpus_status,recorded_at)
     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)""",
     (contract_key,notice_id,notice_sha,version,algorithm_hash,contract_hash,
      event_type,classification,basis,json.dumps(factors,ensure_ascii=False,
      sort_keys=True,separators=(',',':')),source_revision,coverage,now))


def append_scan_snapshot(sidecar:sqlite3.Connection,contract_key:str,
                         matches:list[dict],inspected:int,truncated:bool,
                         revision:str,coverage:str,version:str,
                         rule_hash:str,contract_hash:str,when:str)->None:
    """Record what was actually examined, even if there were no candidates.

    Deduplicate identical checks by rule, contract, imported corpus revision,
    scan coverage and match content. No claim of absence is ever inferred.
    """
    selected=[{
        'idweb':m['idweb'],'notice_sha':m['notice_sha'],
        'status':m['status'],'basis':m['basis'],
        'factors':m.get('rule_evidence') or {},
    } for m in matches]
    selected.sort(key=lambda x:(x['idweb'],x['notice_sha'],x['status']))
    match_digest=hashlib.sha256(json.dumps(selected,ensure_ascii=False,
        sort_keys=True,separators=(',',':')).encode('utf-8')).hexdigest()
    result='pistes_observees' if matches else 'indetermine'
    fields=(version,rule_hash,contract_hash,revision,coverage,
            inspected,len(matches),int(truncated),match_digest,result)
    prior=sidecar.execute("""SELECT algorithm_version,algorithm_sha256,
        contract_sha256,source_revision,corpus_status,scanned_notices,
        matched_notices,truncated,match_signature,result
        FROM local_scan_history WHERE contract_key=?
        ORDER BY scan_id DESC LIMIT 1""",(contract_key,)).fetchone()
    if prior is not None and tuple(prior)==fields:
        return
    sidecar.execute("""INSERT INTO local_scan_history
        (contract_key,algorithm_version,algorithm_sha256,contract_sha256,
         source_revision,corpus_status,scanned_notices,matched_notices,
         truncated,match_signature,result,recorded_at)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?)""",(contract_key,*fields,when))


def utc_now()->str:
    return datetime.now(timezone.utc).isoformat(timespec='seconds')


def source_ready(db:sqlite3.Connection)->None:
    schema=db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
    if not schema or schema[0]!=BOAMP_SCHEMA:
        raise ValueError('Index national BOAMP incompatible')
    if db.execute('SELECT COUNT(*) FROM notices').fetchone()[0]==0:
        raise ValueError('Index BOAMP vide ; importer le flux officiel au préalable')


def complete_official_export(db:sqlite3.Connection)->bool:
    """A probe or date-filtered export is never an exhaustive BOAMP index."""
    return db.execute("""SELECT 1 FROM imports
      WHERE status='complete' AND source_url=? AND records>0 LIMIT 1""",
      (EXPORT,)).fetchone() is not None


def get_contracts(db:sqlite3.Connection,limit:int,offset:int,key:str|None)->list[dict]:
    base='SELECT contract_key,buyer_id,original_id,object,notice_date,cpv,kind,location_type,location_code FROM contracts '
    if key:
        query=db.execute(base+'WHERE contract_key=?',(key,))
    else:
        query=db.execute(base+"""WHERE kind='market' AND LENGTH(original_id)>=6
          AND buyer_id GLOB '[0-9]*' AND LENGTH(buyer_id)=14
          ORDER BY notice_date DESC,contract_key ASC LIMIT ? OFFSET ?""",(limit,offset))
    return [dict(x) for x in query]


def postings(db:sqlite3.Connection,siret:str,max_candidates:int=MAX_LOCAL)->tuple[list[dict],bool]:
    if not re.fullmatch(r'[0-9]{14}',siret):
        return [],False
    # Keep only small metadata in the buyer LRU: whole BOAMP eForms
    # documents can be large and would otherwise exhaust laptop RAM.
    sql="""SELECT n.idweb,n.title,n.source_sha,n.publication_date
      FROM notice_sirets s JOIN notices n ON n.idweb=s.idweb
      WHERE s.siret=? ORDER BY n.publication_date DESC,n.idweb"""
    if max_candidates == 0:
        # Explicit exhaustive local scan. Memory can increase for very
        # large buyers; no evidence is silently excluded by a row cap.
        return [dict(r) for r in db.execute(sql,(siret,))],False
    rows=[dict(r) for r in db.execute(sql+" LIMIT ?",(siret,max_candidates+1))]
    return rows[:max_candidates],len(rows)>max_candidates


def valid_date(year:object)->bool:
    try:return 2010<=int(str(year)[:4])<=datetime.now(timezone.utc).year+1
    except (ValueError,TypeError):return False


def evaluate(contract:dict,notices:list[dict],truncated:bool,
             boamp:sqlite3.Connection,
             short_communes:dict[str,str]|None=None)->list[dict]:
    """Classify all local notice candidates, keep only non-fraudulent claims."""
    # A malformed or futuristic DECP date cannot exclude an otherwise
    # similar official notice. Preserve it in DECP and do not 'repair' it.
    candidate=dict(contract)
    if not valid_date(contract.get('notice_date')):
        candidate['notice_date']=''
    matches=[]
    for row in notices:
        # Fetch one compressed document at a time. The buyer cache never
        # accumulates large document BLOBs, including during long full scans.
        packed=boamp.execute('SELECT compressed_json FROM notice_versions '
            'WHERE idweb=? AND source_sha=?',(row['idweb'],row['source_sha'])).fetchone()
        if packed is None:
            continue
        try:
            source=json.loads(zlib.decompress(packed[0]))
        except (ValueError,zlib.error,TypeError):
            continue
        if not isinstance(source,dict):
            continue
        document=classify(candidate,source,short_communes=short_communes)
        if document is None:
            continue
        document['notice_sha']=row['source_sha']
        matches.append(document)
    # Source exactness outranks title similarity; never merge distinct lots.
    matches.sort(key=lambda m:(0 if m['status']=='procedure_ids' else 1,m['idweb']))
    return matches


def save(sidecar:sqlite3.Connection,contract_key:str,matches:list[dict],
         inspected:int,truncated:bool,source_revision:str,
         source_complete:bool=True,contract:dict|None=None,
         rule_hash:str|None=None)->None:
    """Preserve every algorithm decision transition, including withdrawals.

    When a prior result was stored before this feature, preserve its known
    label as a legacy snapshot, never fabricate its method or its scoring.
    A partially covered archive causes 'not_observed', NOT a disproof.
    """
    now=utc_now()
    coverage='partial' if truncated or not source_complete else 'checked'
    version=ALGORITHM_VERSION
    fingerprint=rule_hash or algorithm_sha256()
    contract_hash=contract_sha256(contract or {'contract_key':contract_key})
    old=sidecar.execute("""SELECT idweb,notice_sha,status,basis
        FROM local_matches WHERE contract_key=? AND active=1
        ORDER BY idweb,notice_sha""",(contract_key,)).fetchall()

    # One-time capture of pre-versioning evidence. Exact previous method,
    # parameters and date cannot be reconstructed from older sidecars.
    for row in old:
        exists=sidecar.execute("""SELECT 1 FROM local_decision_history
            WHERE contract_key=? AND idweb=? LIMIT 1""",
            (contract_key,row['idweb'])).fetchone()
        if exists is None:
            append_history(sidecar,contract_key,row['idweb'],row['notice_sha'],
                           'legacy-unversioned','','','legacy_snapshot',
                           row['status'],row['basis'],{},'unknown','unknown',now)

    # A withdrawal here means "not observed in this recheck". It does not
    # mean the historic BOAMP notice was false or did not exist.
    new_ids={row['idweb'] for row in matches}
    for row in old:
        if row['idweb'] in new_ids:
            continue
        append_history(sidecar,contract_key,row['idweb'],row['notice_sha'],
                       version,fingerprint,contract_hash,'not_observed',
                       'not_observed',
                       'Avis non retenu dans ce nouveau passage ; lien non exclu',
                       {'source_coverage':coverage,'reason':'not_observed_in_current_scan'},
                       source_revision,coverage,now)

    # Retain historic source versions, but only present the current scan.
    sidecar.execute('UPDATE local_matches SET active=0 WHERE contract_key=?',
                    (contract_key,))
    for match in matches:
        previous=sidecar.execute("""SELECT event_type,classification,basis,
            notice_sha,algorithm_sha256,contract_sha256,factors_json
            FROM local_decision_history
            WHERE contract_key=? AND idweb=? ORDER BY event_id DESC LIMIT 1""",
            (contract_key,match['idweb'])).fetchone()
        factors=match.get('rule_evidence') or {}
        factors_json=json.dumps(factors,ensure_ascii=False,sort_keys=True,
                                separators=(',',':'))
        is_same=(previous is not None and
                 previous['event_type']=='observed' and
                 previous['classification']==match['status'] and
                 previous['basis']==match['basis'] and
                 previous['notice_sha']==match['notice_sha'] and
                 previous['algorithm_sha256']==fingerprint and
                 previous['contract_sha256']==contract_hash and
                 previous['factors_json']==factors_json)
        if not is_same:
            append_history(sidecar,contract_key,match['idweb'],
                           match['notice_sha'],version,fingerprint,
                           contract_hash,'observed',match['status'],
                           match['basis'],factors,source_revision,coverage,now)
        sidecar.execute("""INSERT INTO local_matches(contract_key,idweb,notice_sha,status,basis,
          title,url,publication_date,notice_kind,first_seen_at,last_seen_at,active)
          VALUES(?,?,?,?,?,?,?,?,?,?,?,1)
          ON CONFLICT(contract_key,idweb,notice_sha) DO UPDATE SET
          active=1,status=excluded.status,basis=excluded.basis,
          last_seen_at=excluded.last_seen_at""",
          (contract_key,match['idweb'],match['notice_sha'],match['status'],
           match['basis'],match['title'],match['url'],match['publication_date'],
           match['notice_kind'],now,now))
    sidecar.execute("""INSERT INTO local_checks VALUES(?,?,?,?,?,?,?)
      ON CONFLICT(contract_key) DO UPDATE SET checked_at=excluded.checked_at,
      scanned_notices=excluded.scanned_notices,matched_notices=excluded.matched_notices,
      truncated=excluded.truncated,source_revision=excluded.source_revision,
      status=excluded.status""",
      (contract_key,now,inspected,len(matches),int(truncated),source_revision,
       coverage))
    # History of the SCAN itself is separate from individual matching
    # decisions. It records 0-match searches without inventing a BOAMP ID.
    append_scan_snapshot(sidecar,contract_key,matches,inspected,truncated,
                         source_revision,coverage,version,fingerprint,
                         contract_hash,now)

def process(decp:sqlite3.Connection,boamp:sqlite3.Connection,sidecar:sqlite3.Connection,
            records:list[dict],max_candidates:int=MAX_LOCAL,
            short_communes:dict[str,str]|None=None)->dict:
    count=links=truncated=0
    # A typical buyer has many DECP contracts: read the buyer's BOAMP
    # postings once for up to four recent buyers, rather than 1 SQL query
    # per market. Memory remains capped by a modest LRU cache.
    buyer_cache:OrderedDict[str,tuple[list[dict],bool]]=OrderedDict()
    source_revision=boamp.execute('SELECT COALESCE(MAX(import_id),0) FROM imports').fetchone()[0]
    source_complete=complete_official_export(boamp)
    rule_hash=algorithm_sha256()
    for c in records:
        buyer=str(c.get('buyer_id') or '')
        if buyer not in buyer_cache:
            buyer_cache[buyer]=postings(boamp,buyer,max_candidates)
            if len(buyer_cache)>4:buyer_cache.popitem(last=False)
        else:
            buyer_cache.move_to_end(buyer)
        notices,over=buyer_cache[buyer]
        matched=evaluate(c,notices,over,boamp,short_communes=short_communes)
        save(sidecar,str(c['contract_key']),matched,len(notices),over,
             str(source_revision),source_complete=source_complete,
             contract=c,rule_hash=rule_hash)
        count+=1
        links+=len(matched)
        truncated+=over
        if count%250==0:
            # Batched commits avoid hundreds of thousands of fsyncs on HDDs.
            sidecar.commit()
            print('Rapprochement local :',count,'contrats examinés,',
                  links,'avis reliés,',truncated,'couvertures partielles',
                  flush=True)
    sidecar.commit()
    return {'contracts':count,'matches':links,'partial':truncated,
            'partial_source':not source_complete}


def main()->None:
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--contract-key')
    p.add_argument('--resume',action='store_true')
    p.add_argument('--limit',type=int,default=500)
    p.add_argument('--offset',type=int,default=0)
    p.add_argument('--max-notices-per-buyer',type=int,default=MAX_LOCAL)
    p.add_argument('--decp',type=Path,default=NATIONAL)
    p.add_argument('--boamp',type=Path,default=BOAMP)
    p.add_argument('--geography',type=Path,default=GEOGRAPHY,
                   help='COG INSEE 2026 optionnel, en lecture seule (noms de communes courts)')
    p.add_argument('--sidecar',type=Path,default=SIDECAR)
    args=p.parse_args()
    if not 1<=args.limit<=50000 or args.offset<0 or not 0<=args.max_notices_per_buyer<=MAX_LOCAL:
        p.error('limit 1..50000, offset >= 0, max-notices-per-buyer 0 (illimité) ou 1..4000')
    if args.contract_key and not re.fullmatch(r'[a-f0-9]{40}',args.contract_key):
        p.error('Clé contrat : 40 chiffres hexadécimaux')
    if args.resume and (args.contract_key or args.offset):
        p.error('--resume incompatible avec --contract-key et --offset')
    if not args.decp.is_file() or not args.boamp.is_file():
        p.error('Importer DECP et BOAMP d’abord, dans deux SQLite distincts')
    if len({args.decp.resolve(),args.boamp.resolve(),args.sidecar.resolve()})!=3:
        p.error('Les trois bases SQLite doivent être différentes')
    args.sidecar.parent.mkdir(parents=True,exist_ok=True)
    with sqlite3.connect('file:'+str(args.decp.resolve())+'?mode=ro',uri=True) as decp, \
         sqlite3.connect('file:'+str(args.boamp.resolve())+'?mode=ro',uri=True) as boamp, \
         sqlite3.connect(args.sidecar,timeout=30) as side:
        decp.row_factory=boamp.row_factory=side.row_factory=sqlite3.Row
        source_ready(boamp)
        side.execute('PRAGMA busy_timeout=30000')
        side.executescript(OLD_SCHEMA)
        side.executescript(LOCAL_SCHEMA)
        row=side.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
        if row and row[0]!=OLD_VERSION:raise ValueError('Index concordance existant incompatible')
        side.execute("INSERT OR IGNORE INTO metadata VALUES('schema',?)",(OLD_VERSION,))
        cursor=side.execute("SELECT value FROM metadata WHERE key='local_next_offset'").fetchone()
        offset=int(cursor[0]) if args.resume and cursor and cursor[0].isdigit() else args.offset
        records=get_contracts(decp,args.limit,offset,args.contract_key)
        short_places=short_unique_communes(args.geography)
        print('Noms courts INSEE uniques disponibles :',len(short_places),
              '| aucune fusion géographique automatique.')
        outcome=process(decp,boamp,side,records,args.max_notices_per_buyer,
                        short_communes=short_places)
        if args.resume:
            new_offset=0 if len(records)<args.limit else offset+len(records)
            side.execute("""INSERT INTO metadata VALUES('local_next_offset',?)
                ON CONFLICT(key) DO UPDATE SET value=excluded.value""",(str(new_offset),))
            side.commit()
            print('Position de reprise locale:',new_offset)
    print('Contrats:',outcome['contracts'],'| Avis liés:',outcome['matches'],
          '| Recherches incomplètes (trop d’avis):',outcome['partial'],
          '| Source BOAMP partielle:',outcome['partial_source'])
    print('Algorithme :',ALGORITHM_VERSION,'| SHA-256 règles :',algorithm_sha256())
    print('Journaux append-only : local_decision_history et local_scan_history.')
    print('Aucune requête HTTP. Aucune modification DECP/BOAMP. Aucun paiement supposé.')


if __name__=='__main__':
    main()
