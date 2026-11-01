#!/usr/bin/env python3
"""Reconstruct observations from previously imported DECP JSON files, without reimporting contracts.

Run on the V8 working DB, never a DB being served by Symfony.
  python3 tools/backfill_decp_history.py --db /path/work.sqlite --source-dir /path/decp-sources
"""
from __future__ import annotations
import argparse
from pathlib import Path
import sqlite3
import sys
from import_decp import ensure_history_schema, observe, contract_key, stream_array, cents, sha_file

ROOT = Path(__file__).resolve().parents[1]

def backfill(db:sqlite3.Connection, folder:Path) -> dict:
    ensure_history_schema(db)
    result={'processed':0,'skipped':0,'missing':0,'snapshots':0,'invalid':0}
    imports=db.execute('SELECT sha256,filename FROM imports ORDER BY filename,sha256').fetchall()
    for sha,filename in imports:
        expected=db.execute('SELECT COUNT(*) FROM contract_sources WHERE source_sha=?',(sha,)).fetchone()[0]
        existing=db.execute('SELECT COUNT(*) FROM contract_publications WHERE source_sha=?',(sha,)).fetchone()[0]
        if existing>=expected:
            result['skipped']+=1;continue
        file=folder/filename
        if not file.is_file():
            print('SOURCE ABSENTE :',file,flush=True);result['missing']+=1;continue
        if sha_file(file)!=sha:
            print('SHA-256 NON CONFORME :',file,flush=True);result['invalid']+=1;continue
        print('Historique :',filename,flush=True)
        # The full file is re-parsed; only observations are written.
        # A per-file SAVEPOINT makes a truncated JSON rollback entirely.
        db.execute('SAVEPOINT backfill_file')
        try:
            # Discard partial prior attempts for a deterministic reconstruction.
            db.execute('DELETE FROM contract_publications WHERE source_sha=?',(sha,))
            for group,kind in [('marche','market'),('contrat-concession','concession')]:
                for item in stream_array(file,group):
                    key=contract_key(item,kind)
                    if not key:continue
                    observe(db,key,sha,item,kind,cents)
            actual=db.execute('SELECT COUNT(*) FROM contract_publications WHERE source_sha=?',(sha,)).fetchone()[0]
            if actual!=expected:
                raise ValueError(f'Incohérence : {actual} contrats historiques, {expected} sources indexées')
            db.execute('RELEASE backfill_file')
            db.commit()
            result['processed']+=1;result['snapshots']+=actual
            print('  OK',actual,'contrats',flush=True)
        except (OSError,ValueError,UnicodeError,sqlite3.DatabaseError) as exc:
            db.execute('ROLLBACK TO backfill_file');db.execute('RELEASE backfill_file');db.rollback()
            print('  IGNORÉ —',exc,flush=True);result['invalid']+=1
    return result

def main():
    ap=argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--db',type=Path,default=ROOT/'data/research/public-procurement.sqlite')
    ap.add_argument('--source-dir',type=Path,default=ROOT/'data/research/decp-sources')
    args=ap.parse_args()
    if not args.db.is_file():sys.exit('ERREUR : index absent. Ne pas créer de base vide: '+str(args.db))
    if not args.source_dir.is_dir():sys.exit('ERREUR : dossier JSON absent: '+str(args.source_dir))
    with sqlite3.connect(f'file:{args.db.resolve()}?mode=rw',uri=True,timeout=60) as db:
        if db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone() != ('populorum-decp-1',):
            sys.exit('ERREUR : index DECP non reconnu')
        print('Bilan:',backfill(db,args.source_dir))

if __name__=='__main__':main()
