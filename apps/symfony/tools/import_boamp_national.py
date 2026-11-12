#!/usr/bin/env python3
"""Stream the official national BOAMP export into a separate, versioned SQLite index.

Source: DILA, OpenDataSoft Explore v2.1 /catalog/datasets/boamp/exports/jsonl
Only Python stdlib. No write whatsoever to public-procurement.sqlite.

From apps/symfony:
  python3 tools/import_boamp_national.py --download --full
  python3 tools/import_boamp_national.py --download --since 2026-11-01
  python3 tools/import_boamp_national.py --file /path/boamp.jsonl
  python3 tools/import_boamp_national.py --stats

An interrupted download retains completed 250-row commits and can be rerun.
A rerun is idempotent; one notice can have several distinct source versions.
This is a public-avis archive, never an accounting / payment ledger.
"""
from __future__ import annotations
import argparse
from datetime import date,datetime,timezone
import gzip
import hashlib
import io
import json
from pathlib import Path
import re
import shutil
import sqlite3
import sys
from urllib.parse import urlencode,urlsplit
from urllib.request import Request,urlopen
import zlib

ROOT=Path(__file__).resolve().parents[1]
DEFAULT_DB=ROOT/'data/research/boamp-national.sqlite'
OFFICIAL_HOST='boamp-datadila.opendatasoft.com'
EXPORT='https://'+OFFICIAL_HOST+'/api/explore/v2.1/catalog/datasets/boamp/exports/jsonl'
SCHEMA_VERSION='populorum-boamp-national-1'
SCHEMA="""
CREATE TABLE IF NOT EXISTS metadata(key TEXT PRIMARY KEY,value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS notices(
 idweb TEXT PRIMARY KEY,source_sha TEXT NOT NULL,
 title TEXT NOT NULL,buyer_name TEXT NOT NULL,holder_name TEXT NOT NULL,
 publication_date TEXT NOT NULL,notice_kind TEXT NOT NULL,folder_id TEXT NOT NULL,
 url TEXT NOT NULL,first_imported_at TEXT NOT NULL,last_imported_at TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS notices_date ON notices(publication_date DESC,idweb ASC);
CREATE INDEX IF NOT EXISTS notices_folder ON notices(folder_id,idweb);
CREATE TABLE IF NOT EXISTS notice_versions(
 idweb TEXT NOT NULL,source_sha TEXT NOT NULL,source_url TEXT NOT NULL,
 first_seen_at TEXT NOT NULL,compressed_json BLOB NOT NULL,
 PRIMARY KEY(idweb,source_sha));
CREATE TABLE IF NOT EXISTS notice_sirets(
 siret TEXT NOT NULL,idweb TEXT NOT NULL,
 PRIMARY KEY(siret,idweb));
CREATE INDEX IF NOT EXISTS notice_sirets_notice ON notice_sirets(idweb);
CREATE TABLE IF NOT EXISTS notice_relations(
 idweb TEXT NOT NULL,related_idweb TEXT NOT NULL,relation TEXT NOT NULL,
 PRIMARY KEY(idweb,related_idweb,relation));
CREATE TABLE IF NOT EXISTS imports(
 import_id INTEGER PRIMARY KEY AUTOINCREMENT,source_url TEXT NOT NULL,
 started_at TEXT NOT NULL,finished_at TEXT NOT NULL,
 records INTEGER NOT NULL,versions_added INTEGER NOT NULL,
 stream_sha256 TEXT NOT NULL,status TEXT NOT NULL);
"""


def utc_now()->str:
    return datetime.now(timezone.utc).isoformat(timespec='seconds')


def parse_date(s:object)->str:
    """Keep the original date string, not an invented or fixed year."""
    return str(s or '')[:80]


def json_object(record:object)->dict:
    if not isinstance(record,dict):
        raise ValueError('Export BOAMP : objet JSON attendu')
    if 'record' in record and isinstance(record['record'],dict):
        nested=record['record']
        if isinstance(nested.get('fields'),dict):
            return nested['fields']
    if isinstance(record.get('fields'),dict) and 'idweb' not in record:
        return record['fields']
    return record


def source_json(row:dict)->bytes:
    return json.dumps(row,ensure_ascii=False,sort_keys=True,separators=(',',':'),default=str).encode('utf-8')


def valid_notice_url(value:object)->str:
    if not isinstance(value,str):return ''
    parts=urlsplit(value)
    if parts.scheme!='https' or parts.hostname not in ('www.boamp.fr','boamp.fr') or parts.username or parts.password or len(value)>2048:
        return ''
    return value


def notice_sirets(payload:dict)->set[str]:
    # SIRETs cited anywhere in the source, NOT necessarily buyer SIRETs.
    # The local matcher still checks contractual context before asserting
    # even a *procedure-level* association.
    raw=source_json({'donnees':payload.get('donnees'), 'gestion':payload.get('gestion')})
    return {s.decode('ascii') for s in re.findall(rb'(?<![0-9])[0-9]{14}(?![0-9])',raw)}


def idweb_values(value:object)->set[str]:
    if not value:return set()
    raw=json.dumps(value,ensure_ascii=False)
    return set(re.findall(r'(?<![0-9])[0-9]{2}-[0-9]{3,8}(?![0-9])',raw))


def normalize(row:dict)->dict|None:
    row=json_object(row)
    # In JSONL output, user-facing column names are lowercase field IDs.
    key=str(row.get('idweb') or '')
    if not re.fullmatch(r'[0-9]{2}-[0-9]{3,8}',key):
        return None
    url=valid_notice_url(row.get('url_avis'))
    if not url:
        # A missing/unsafe provenance URL means the notice cannot be shown
        # as an official source, so do not silently synthesize one.
        return None
    cleaned=dict(row)
    # Do not mutate the raw result: compressed source preserves fields
    # and structures (including eForms data and notice identifiers).
    for field in ('donnees','gestion'):
        if isinstance(cleaned.get(field),str):
            try:cleaned[field]=json.loads(cleaned[field])
            except json.JSONDecodeError:pass
    raw=source_json(cleaned)
    digest=hashlib.sha256(raw).hexdigest()
    rel=set()
    for name in ('annonce_lie','annonces_anterieures'):
        for ref in idweb_values(row.get(name)):
            if ref!=key:rel.add((ref,name))
    return {'idweb':key,'source_sha':digest,'source_json':raw,
       'title':str(row.get('objet') or '')[:600],
       'buyer_name':str(row.get('nomacheteur') or '')[:400],
       'holder_name':str(row.get('titulaire') or '')[:400],
       'publication_date':parse_date(row.get('dateparution')),
       'notice_kind':str(row.get('nature_categorise_libelle') or row.get('type_avis') or '')[:120],
       'folder_id':str(row.get('contractfolderid') or '')[:100],
       'url':url,'sirets':notice_sirets(cleaned),'relations':rel}


def open_db(path:Path)->sqlite3.Connection:
    path.parent.mkdir(parents=True,exist_ok=True)
    db=sqlite3.connect(path,timeout=30)
    db.execute('PRAGMA busy_timeout=30000')
    db.execute('PRAGMA journal_mode=WAL')
    db.executescript(SCHEMA)
    schema=db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
    if schema is None:
        db.execute('INSERT INTO metadata VALUES(?,?)',('schema',SCHEMA_VERSION))
        db.commit()
    elif schema[0]!=SCHEMA_VERSION:
        db.close()
        raise ValueError('Schema BOAMP incompatible : '+str(schema[0]))
    return db


def upsert_notice(db:sqlite3.Connection,notice:dict,source_url:str,now:str)->bool:
    key=notice['idweb']
    sha=notice['source_sha']
    cur=db.execute("""INSERT OR IGNORE INTO notice_versions
        (idweb,source_sha,source_url,first_seen_at,compressed_json)
        VALUES(?,?,?,?,?)""",(key,sha,source_url,now,zlib.compress(notice['source_json'],3)))
    new_version=cur.rowcount==1
    # Not replacing the current snapshot when we reimport an old version:
    # the dated version history remains authoritative for investigations.
    current=db.execute('SELECT source_sha FROM notices WHERE idweb=?',(key,)).fetchone()
    if current is None:
        db.execute("""INSERT INTO notices VALUES(?,?,?,?,?,?,?,?,?,?,?)""",
          (key,sha,notice['title'],notice['buyer_name'],notice['holder_name'],
           notice['publication_date'],notice['notice_kind'],notice['folder_id'],
           notice['url'],now,now))
    elif current[0]!=sha:
        # A corrected notice can legitimately revert to an earlier content
        # version. Keep that source version and make the latest received
        # official export authoritative for the current display snapshot.
        db.execute("""UPDATE notices SET source_sha=?,title=?,buyer_name=?,
          holder_name=?,publication_date=?,notice_kind=?,folder_id=?,url=?,
          last_imported_at=? WHERE idweb=?""",
          (sha,notice['title'],notice['buyer_name'],notice['holder_name'],
           notice['publication_date'],notice['notice_kind'],notice['folder_id'],
           notice['url'],now,key))
    # When source changes, postings for this ID also change. Reinsert the
    # current snapshot's SIRETs; do not accumulate IDs removed by corrections.
    if current is None or current[0]!=sha:
        db.execute('DELETE FROM notice_sirets WHERE idweb=?',(key,))
        db.executemany('INSERT OR IGNORE INTO notice_sirets VALUES(?,?)',
            [(s,key) for s in notice['sirets']])
        db.execute('DELETE FROM notice_relations WHERE idweb=?',(key,))
        db.executemany('INSERT OR IGNORE INTO notice_relations VALUES(?,?,?)',
            [(key,ref,relation) for ref,relation in notice['relations']])
    return new_version


def iter_jsonl(stream:io.BufferedIOBase,max_record_bytes:int=16_000_000):
    """JSONL streaming: no full export materialization or all-records list."""
    line_no=0
    while True:
        # A bounded readline avoids allocating an arbitrarily large line
        # before checking the maximum record size.
        raw=stream.readline(max_record_bytes+1)
        if not raw:
            break
        line_no+=1
        if len(raw)>max_record_bytes:
            raise ValueError('Ligne BOAMP trop volumineuse : '+str(line_no))
        if not raw.strip():
            continue
        try:
            obj=json.loads(raw)
        except (ValueError,UnicodeDecodeError) as e:
            raise ValueError('JSONL invalide, ligne '+str(line_no)) from e
        yield obj


def import_jsonl(db:sqlite3.Connection,stream,source_url:str,
                 batch_size:int=250,max_records:int|None=None,
                 db_file:Path|None=None,min_free_gb:int=2)->tuple[int,int,str]:
    if batch_size<1 or batch_size>2000:raise ValueError('batch size 1..2000')
    started=utc_now()
    stream_hash=hashlib.sha256()
    seen=added=skipped=0
    # Commit in small units so long exports can be interrupted and re-run.
    # No modifications whatsoever to public-procurement.sqlite.
    for record in iter_jsonl(stream):
        row=json_object(record)
        value=source_json(row)
        stream_hash.update(value)
        stream_hash.update(b'\n')
        notice=normalize(row)
        if notice is not None:
            if upsert_notice(db,notice,source_url,utc_now()):
                added+=1
        else:
            # A skipped announcement makes the index incomplete, even if
            # the HTTP export was delivered without transport errors.
            skipped+=1
        seen+=1
        if seen%batch_size==0:
            db.commit()
            if seen%5000==0:
                free = shutil.disk_usage(db_file.parent).free if db_file else -1
                print('BOAMP importé:',seen,'avis ;',added,'versions nouvelles ;',
                      'libre:',f'{free/1024**3:.1f} GiB' if free>=0 else 'non mesuré',
                      flush=True)
                if db_file and free < min_free_gb*1024**3:
                    raise OSError('Espace disque insuffisant pour poursuivre ; '
                                  'les lots précédents sont sauvegardés.')
        if max_records is not None and seen>=max_records:break
    db.execute("""INSERT INTO imports(source_url,started_at,finished_at,
      records,versions_added,stream_sha256,status)
      VALUES(?,?,?,?,?,?,?)""",
      (source_url,started,utc_now(),seen,added,stream_hash.hexdigest(),
       'partial' if skipped or not seen else ('complete' if max_records is None else 'sample')))
    db.commit()
    if skipped:
        print('ATTENTION :',skipped,'avis sans identifiant ou URL BOAMP sûre ; '
              'export indexé partiellement, absence de lien indéterminée.',flush=True)
    return seen,added,stream_hash.hexdigest()


def stream_from_url(url:str):
    request=Request(url,headers={
        'Accept':'application/x-ndjson,application/jsonl,application/json',
        'User-Agent':'Populorum-BOAMP-National/1.0 (official public open data)',
    })
    response=urlopen(request,timeout=90)
    redirect=urlsplit(response.url)
    if redirect.scheme!='https' or redirect.hostname!=OFFICIAL_HOST:
        response.close()
        raise ValueError('Redirection hors de la source BOAMP officielle')
    return response


def export_url(since:str|None,probe:bool=False)->str:
    params={}
    if probe:
        params['where']="idweb = '24-30283'"
    elif since:
        # Opendatasoft ODSQL date literal on dateparution.
        d=date.fromisoformat(since)
        params['where']="dateparution >= date'"+d.isoformat()+"'"
    return EXPORT+('?' + urlencode(params) if params else '')


def main():
    p=argparse.ArgumentParser(description=__doc__)
    choice=p.add_mutually_exclusive_group(required=True)
    choice.add_argument('--download',action='store_true',help='Exporter BOAMP officiel')
    choice.add_argument('--file',type=Path,help='JSONL officiel préalablement téléchargé (.gz accepté)')
    choice.add_argument('--stats',action='store_true')
    p.add_argument('--full',action='store_true',help='Premier export complet, potentiellement volumineux')
    p.add_argument('--since',help='Inclure tous les avis depuis cette date (AAAA-MM-JJ)')
    p.add_argument('--probe',action='store_true',help='Test de lecture d’un avis officiel')
    p.add_argument('--db',type=Path,default=DEFAULT_DB)
    p.add_argument('--min-free-gb',type=int,default=2,help='Arrêter l’import avant de saturer le disque')
    args=p.parse_args()
    if not 1<=args.min_free_gb<=100:
        p.error('--min-free-gb doit être entre 1 et 100')
    if args.download and sum([bool(args.full),bool(args.since),bool(args.probe)])!=1:
        p.error('Avec --download, indiquer exactement --full, --since ou --probe')
    if (args.full or args.since or args.probe) and not args.download:
        p.error('Ces options nécessitent --download')
    if args.db.resolve()==(ROOT/'data/research/public-procurement.sqlite').resolve():
        p.error('Interdiction de remplacer la base DECP')
    if args.since:
        try:date.fromisoformat(args.since)
        except ValueError:p.error('Date --since invalide')
    if args.stats:
        if not args.db.is_file():p.error('Index BOAMP inexistant')
        with sqlite3.connect('file:'+str(args.db.resolve())+'?mode=ro',uri=True) as db:
            for label,sql in [('avis','SELECT COUNT(*) FROM notices'),
                               ('versions','SELECT COUNT(*) FROM notice_versions'),
                               ('sirets','SELECT COUNT(*) FROM notice_sirets'),
                               ('imports','SELECT COUNT(*) FROM imports')]:
                print(label+':',db.execute(sql).fetchone()[0])
        return
    args.db.parent.mkdir(parents=True,exist_ok=True)
    if shutil.disk_usage(args.db.parent).free < args.min_free_gb*1024**3:
        p.error('Espace disque insuffisant pour l’import (voir --min-free-gb)')
    with open_db(args.db) as db:
        if args.file:
            if not args.file.is_file():p.error('Fichier JSONL introuvable')
            open_fn=gzip.open if args.file.suffix=='.gz' else open
            with open_fn(args.file,'rb') as f:
                seen,added,sha=import_jsonl(db,f,args.file.resolve().as_uri(),
                                                 db_file=args.db,min_free_gb=args.min_free_gb)
        else:
            url=export_url(args.since,args.probe)
            with stream_from_url(url) as f:
                seen,added,sha=import_jsonl(db,f,url,db_file=args.db,min_free_gb=args.min_free_gb)
    print('Avis lus:',seen,'| Nouvelles versions:',added,'| SHA-256 de l’import:',sha)
    print('Index national BOAMP indépendant:',args.db)
    print('Aucun paiement déduit; base nationale DECP intacte.')


if __name__=='__main__':
    main()
