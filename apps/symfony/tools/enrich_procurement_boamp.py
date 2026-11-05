#!/usr/bin/env python3
"""Find BOAMP notice *candidates* for indexed DECP contracts.

Offline batch task; NEVER called from a web request. A text-search match is not
evidence of award, purpose, payment or result. Only public BOAMP notice links
returned by the official API are retained, clearly marked "candidate".
"""
from __future__ import annotations
import argparse
import json
from pathlib import Path
import re
import sqlite3
import time
from urllib.parse import urlencode, urlsplit
from urllib.request import Request, urlopen

from import_procurement_evidence import import_evidence

ROOT=Path(__file__).resolve().parents[1]
API='https://boamp-datadila.opendatasoft.com/api/explore/v2.1/catalog/datasets/boamp/records'
USER_AGENT='Populorum-Public-Procurement-Research/2.0 (public notice citations)'
STOP={'pour','avec','dans','des','les','une','sur','aux','travaux','marche',
      'fourniture','services','prestation','prestations','lot','renovation',
      'installation','commune','publics','public','organisation'}


def tokens(value: str) -> list[str]:
    import unicodedata
    norm=unicodedata.normalize('NFKD', value.casefold())
    norm=''.join(c for c in norm if not unicodedata.combining(c))
    return [s for s in re.findall(r'[a-z0-9]{4,}',norm)
            if s not in STOP and not s.isdigit()]


def similarity(a: str,b: str) -> float:
    one,two=set(tokens(a)),set(tokens(b))
    if len(one&two)<3:
        return 0.0
    return len(one&two)/len(one|two) if one and two else 0.0


def query_boamp(object_name: str, timeout: float = 12.0) -> list[dict]:
    terms=list(dict.fromkeys(tokens(object_name)))
    if len(terms)<3:
        return []
    # Opendatasoft Explore v2.1 where expression. A short, bounded literal is
    # escaped and URL-encoded; user input never becomes SQL or a destination URL.
    term=' '.join(terms[:3])[:90].replace("'", "''")
    params=urlencode({'where':"search(objet, '"+term+"')",'limit':20,
                      'select':'idweb,objet,nomacheteur,dateparution,url_avis,nature_categorise_libelle'})
    request=Request(API+'?'+params,headers={'User-Agent':USER_AGENT,'Accept':'application/json'})
    with urlopen(request,timeout=timeout) as response:
        if urlsplit(response.url).hostname != 'boamp-datadila.opendatasoft.com':
            raise ValueError('Redirection API BOAMP inattendue')
        payload=response.read(1_000_001)
        if len(payload)>1_000_000:
            raise ValueError('Réponse BOAMP excessive')
    parsed=json.loads(payload)
    return parsed.get('results',[]) if isinstance(parsed,dict) and isinstance(parsed.get('results',[]),list) else []


def candidates(key: str, object_name: str, result: list[dict]) -> list[dict]:
    found=[]
    for notice in result:
        if not isinstance(notice,dict):
            continue
        url=notice.get('url_avis')
        if not isinstance(url,str):
            continue
        u=urlsplit(url)
        if u.scheme!='https' or u.hostname not in ('www.boamp.fr','boamp.fr'):
            continue
        if similarity(object_name,str(notice.get('objet') or '')) < 0.46:
            continue
        found.append({
            'contract_key':key,'category':'context','source_type':'boamp',
            'status':'candidate','source_name':'DILA / BOAMP',
            'source_url':url,'document_title':str(notice.get('objet') or 'Avis BOAMP')[:280],
            'statement':'','excerpt':'','published_at':'',
        })
    return found


def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--db',type=Path,default=ROOT/'data/research/public-procurement-v2-staging.sqlite')
    p.add_argument('--limit',type=int,default=10,help='Nombre maximal de contrats interrogés (1 à 100)')
    p.add_argument('--contract-key',help='Empreinte SHA-256 tronquée à 40 caractères issue de Populorum')
    p.add_argument('--pause',type=float,default=1.0,help='Pause entre requêtes BOAMP (minimum 0,5 s)')
    opts=p.parse_args()
    if not 1 <= opts.limit <= 100 or opts.pause < .5:
        p.error('Limite 1-100 et pause >= 0,5 s.')
    with sqlite3.connect(opts.db,timeout=30) as db:
        if db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()!=('populorum-decp-2',):
            raise ValueError('Base DECP v2 obligatoire.')
        if opts.contract_key:
            if not re.fullmatch(r'[a-f0-9]{40}',opts.contract_key):
                raise ValueError('Clé invalide.')
            records=db.execute('SELECT contract_key,object FROM contracts WHERE contract_key=?',
                               (opts.contract_key,)).fetchall()
        else:
            records=db.execute('''SELECT c.contract_key,c.object FROM contracts c
                WHERE c.kind='market' AND NOT EXISTS (
                    SELECT 1 FROM procurement_evidence e
                    WHERE e.contract_key=c.contract_key AND e.source_type='boamp')
                ORDER BY c.notice_date DESC,c.contract_key LIMIT ?''',(opts.limit,)).fetchall()
        for i,(key,title) in enumerate(records):
            try:
                results=query_boamp(title)
                matches=candidates(key,title,results)
                new=import_evidence(db,matches)
                db.commit()
                print(f'{i+1}/{len(records)} {key}: {len(matches)} correspondances candidates, {new} nouvelles.',flush=True)
            except (ValueError,OSError,json.JSONDecodeError) as exc:
                print(f'{key}: recherche BOAMP indisponible ({exc}); aucun document inventé.',flush=True)
            if i+1<len(records):
                time.sleep(opts.pause)


if __name__=='__main__':
    main()
