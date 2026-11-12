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
from collections import OrderedDict
from datetime import datetime,timezone
import json
from pathlib import Path
import re
import sqlite3
import zlib
from import_boamp_national import DEFAULT_DB as BOAMP,SCHEMA_VERSION as BOAMP_SCHEMA,EXPORT
from reconcile_procurement_boamp import (NATIONAL, SIDECAR, SCHEMA as OLD_SCHEMA,
                                        SCHEMA_VERSION as OLD_VERSION,
                                        classify,terms)

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
"""
MAX_LOCAL=4000


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
    base='SELECT contract_key,buyer_id,original_id,object,notice_date,cpv,kind FROM contracts '
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
             boamp:sqlite3.Connection)->list[dict]:
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
        document=classify(candidate,source)
        if document is None:
            continue
        document['notice_sha']=row['source_sha']
        matches.append(document)
    # Source exactness outranks title similarity; never merge distinct lots.
    matches.sort(key=lambda m:(0 if m['status']=='procedure_ids' else 1,m['idweb']))
    return matches


def save(sidecar:sqlite3.Connection,contract_key:str,matches:list[dict],
         inspected:int,truncated:bool,source_revision:str,
         source_complete:bool=True)->None:
    now=utc_now()
    # Previously linked documents are retained as historical observations.
    # On rescans they may become inactive but are never discarded.
    sidecar.execute('UPDATE local_matches SET active=0 WHERE contract_key=?',(contract_key,))
    for match in matches:
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
       'partial' if truncated or not source_complete else 'checked'))


def process(decp:sqlite3.Connection,boamp:sqlite3.Connection,sidecar:sqlite3.Connection,
            records:list[dict],max_candidates:int=MAX_LOCAL)->dict:
    count=links=truncated=0
    # A typical buyer has many DECP contracts: read the buyer's BOAMP
    # postings once for up to four recent buyers, rather than 1 SQL query
    # per market. Memory remains capped by a modest LRU cache.
    buyer_cache:OrderedDict[str,tuple[list[dict],bool]]=OrderedDict()
    source_revision=boamp.execute('SELECT COALESCE(MAX(import_id),0) FROM imports').fetchone()[0]
    source_complete=complete_official_export(boamp)
    for c in records:
        buyer=str(c.get('buyer_id') or '')
        if buyer not in buyer_cache:
            buyer_cache[buyer]=postings(boamp,buyer,max_candidates)
            if len(buyer_cache)>4:buyer_cache.popitem(last=False)
        else:
            buyer_cache.move_to_end(buyer)
        notices,over=buyer_cache[buyer]
        matched=evaluate(c,notices,over,boamp)
        save(sidecar,str(c['contract_key']),matched,len(notices),over,
             str(source_revision),source_complete=source_complete)
        count+=1
        links+=len(matched)
        truncated+=over
        if count%250==0:
            # Batched commits avoid hundreds of thousands of fsyncs on HDDs.
            sidecar.commit()
            print('Rapprochement local :',count,'contrats examinés,'
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
        outcome=process(decp,boamp,side,records,args.max_notices_per_buyer)
        if args.resume:
            new_offset=0 if len(records)<args.limit else offset+len(records)
            side.execute("""INSERT INTO metadata VALUES('local_next_offset',?)
                ON CONFLICT(key) DO UPDATE SET value=excluded.value""",(str(new_offset),))
            side.commit()
            print('Position de reprise locale:',new_offset)
    print('Contrats:',outcome['contracts'],'| Avis liés:',outcome['matches'],
          '| Recherches incomplètes (trop d’avis):',outcome['partial'],
          '| Source BOAMP partielle:',outcome['partial_source'])
    print('Aucune requête HTTP. Aucune modification DECP/BOAMP. Aucun paiement supposé.')


if __name__=='__main__':
    main()
