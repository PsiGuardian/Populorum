#!/usr/bin/env python3
"""Checks the geographic reference independently of Symfony and financial data."""
from pathlib import Path
import sqlite3
import sys

path = Path(__file__).resolve().parents[1] / 'data/geography/communes-2026.sqlite'
if not path.is_file():
    sys.exit('ABSENT : référentiel COG 2026. Exécutez python3 tools/import_communes_insee.py --download')
try:
    conn = sqlite3.connect(f'file:{path}?mode=ro', uri=True)
    year = conn.execute("SELECT value FROM metadata WHERE key='vintage'").fetchone()
    count = conn.execute('SELECT COUNT(*) FROM communes').fetchone()[0]
    valid = count >= 30000 and year and year[0] == '2026'
    paris = conn.execute('SELECT name FROM communes WHERE code=?', ('75056',)).fetchone()
    havre = conn.execute('SELECT name FROM communes WHERE code=?', ('76351',)).fetchone()
    marseille = conn.execute('SELECT name FROM communes WHERE code=?', ('13055',)).fetchone()
    for code, row in [('75056',paris),('76351',havre),('13055',marseille)]:
        valid &= row is not None
        print(code, row[0] if row else 'MANQUANTE')
    print('Communes INSEE :', f'{count:,}'.replace(',', ' '), '| Millésime :', year[0] if year else '?')
    print('Régions :', conn.execute('SELECT COUNT(DISTINCT region_code) FROM communes').fetchone()[0])
    print('Intégrité :', conn.execute('PRAGMA integrity_check').fetchone()[0])
    if not valid:
        sys.exit('ÉCHEC : import incomplet ou non compatible.')
    print('OK : référentiel national disponible. Le budget exige des sources financières séparées.')
except sqlite3.DatabaseError as exc:
    sys.exit('ÉCHEC SQLite : ' + str(exc))
