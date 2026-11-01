#!/usr/bin/env python3
"""Shared, read-only integrity checks for the Populorum DECP SQLite index."""
from __future__ import annotations

from pathlib import Path
from contextlib import closing
import sqlite3

SCHEMA_VERSION = 'populorum-decp-1'
REQUIRED_TABLES = ('metadata', 'imports', 'contracts', 'changes', 'participants',
                   'contract_sources', 'concession_finance', 'contract_fts')

class IndexErrorDetail(Exception):
    """Invalid or incompatible DECP database; never an instruction to delete it."""


def inspect_database(path: Path, *, check_links: bool = True) -> dict:
    path = path.expanduser().resolve()
    if not path.is_file():
        raise IndexErrorDetail(f'Index absent : {path}')
    if path.stat().st_size < 4096:
        raise IndexErrorDetail(f'Index vide ou trop petit ({path.stat().st_size} octets) : {path}')
    try:
        # mode=ro prevents SQLite from silently creating an empty database.
        with closing(sqlite3.connect(path.as_uri() + '?mode=ro', uri=True)) as db:
            db.execute('PRAGMA query_only=ON')
            integrity = db.execute('PRAGMA quick_check').fetchone()
            if not integrity or integrity[0] != 'ok':
                raise IndexErrorDetail(f'Intégrité SQLite non valide : {integrity}')
            tables = {r[0] for r in db.execute("SELECT name FROM sqlite_master WHERE type IN ('table','view')")}
            missing = sorted(set(REQUIRED_TABLES) - tables)
            if missing:
                raise IndexErrorDetail('Schéma DECP absent ou incomplet : ' + ', '.join(missing)
                                       + '. Tables présentes : ' + (', '.join(sorted(tables)) or '(aucune)'))
            row = db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
            if row is None or row[0] != SCHEMA_VERSION:
                raise IndexErrorDetail(f'Version de schéma incompatible : {row[0] if row else "absente"}')
            counts = {table: db.execute(f'SELECT COUNT(*) FROM {table}').fetchone()[0]
                      for table in ('imports','contracts','changes','participants','contract_sources','concession_finance')}
            if counts['imports'] < 1 or counts['contracts'] < 1:
                raise IndexErrorDetail('Schéma présent, mais aucun import ou contrat enregistré.')
            if check_links:
                checks = {
                    'types de contrat inconnus': "SELECT COUNT(*) FROM contracts WHERE kind NOT IN ('market','concession')",
                    'contrats sans source': 'SELECT COUNT(*) FROM contracts WHERE contract_key NOT IN (SELECT contract_key FROM contract_sources)',
                    'sources sans contrat': 'SELECT COUNT(*) FROM contract_sources WHERE contract_key NOT IN (SELECT contract_key FROM contracts)',
                    'sources sans import': 'SELECT COUNT(*) FROM contract_sources WHERE source_sha NOT IN (SELECT sha256 FROM imports)',
                    'modifications orphelines': 'SELECT COUNT(*) FROM changes WHERE contract_key NOT IN (SELECT contract_key FROM contracts)',
                    'participants orphelins': 'SELECT COUNT(*) FROM participants WHERE contract_key NOT IN (SELECT contract_key FROM contracts)',
                }
                for name, sql in checks.items():
                    n = db.execute(sql).fetchone()[0]
                    if n:
                        raise IndexErrorDetail(f'{name}: {n}')
                nfts = db.execute('SELECT COUNT(*) FROM contract_fts').fetchone()[0]
                if nfts != counts['contracts']:
                    raise IndexErrorDetail(f'FTS désynchronisé : {nfts} documents pour {counts["contracts"]} contrats')
            return {'path': str(path), 'size': path.stat().st_size, 'counts': counts, 'schema': SCHEMA_VERSION}
    except IndexErrorDetail:
        raise
    except sqlite3.Error as e:
        raise IndexErrorDetail(f'Erreur SQLite sur {path}: {e}') from e
