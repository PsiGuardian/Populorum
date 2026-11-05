#!/usr/bin/env python3
"""Read-only validation of DECP snapshot history; never creates databases."""
from __future__ import annotations
import argparse
from pathlib import Path
import sqlite3
import sys

ROOT=Path(__file__).resolve().parents[1]

def verify(path:Path) -> bool:
    if not path.is_file():
        print('ERREUR : base absente :',path);return False
    with sqlite3.connect('file:'+str(path.resolve())+'?mode=ro',uri=True,timeout=10) as db:
        db.execute('PRAGMA query_only=ON')
        names={row[0] for row in db.execute("SELECT name FROM sqlite_master WHERE type IN ('table','view')")}
        required={'metadata','contracts','imports','contract_sources','contract_publications'}
        if not required<=names:
            print('ERREUR : tables absentes :',', '.join(sorted(required-names)));return False
        if db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()!=('populorum-decp-2',):
            print('ERREUR : schéma DECP non reconnu');return False
        contracts=db.execute('SELECT COUNT(*) FROM contracts').fetchone()[0]
        appearances=db.execute('SELECT COUNT(*) FROM contract_sources').fetchone()[0]
        observations=db.execute('SELECT COUNT(*) FROM contract_publications').fetchone()[0]
        missing=db.execute('''SELECT COUNT(*) FROM contract_sources cs LEFT JOIN contract_publications cp
            ON cp.contract_key=cs.contract_key AND cp.source_sha=cs.source_sha
            WHERE cp.contract_key IS NULL''').fetchone()[0]
        invalid=db.execute('''SELECT COUNT(*) FROM contract_publications cp LEFT JOIN contract_sources cs
            ON cp.contract_key=cs.contract_key AND cp.source_sha=cs.source_sha
            WHERE cs.contract_key IS NULL''').fetchone()[0]
        imported=db.execute('SELECT COUNT(*) FROM imports').fetchone()[0]
        print('Index :',path)
        print('Contrats :',f'{contracts:,}'.replace(',',' '),'· fichiers importés :',imported)
        print('Apparitions documentées :',appearances,'· observations historiques :',observations)
        print('Observations manquantes :',missing,'· observations orphelines :',invalid)
        if invalid:
            print('ERREUR : références historiques sans source indexée.');return False
        if missing:
            print('PARTIEL — Lancez backfill_decp_history.py sur la base de travail, puis recommencez.')
            return False
        print('OK — historique complet pour tous les couples contrat / fichier présents dans cet index.')
        return True

def main():
    ap=argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--db',type=Path,default=ROOT/'data/research/public-procurement-v8-staging.sqlite')
    args=ap.parse_args()
    if not verify(args.db):sys.exit(1)
if __name__=='__main__':main()
