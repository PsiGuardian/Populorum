#!/usr/bin/env python3
"""Read-only verification for INSEE / La Poste correspondence."""
from pathlib import Path
import argparse
import sqlite3
import sys

ROOT = Path(__file__).resolve().parents[1]
DEFAULT = ROOT / 'data/geography/communes-2026.sqlite'


def check(path: Path) -> int:
    if not path.is_file() or path.stat().st_size < 4096:
        print('ERREUR : référentiel INSEE manquant ou vide :', path)
        print('Commande : python3 tools/import_communes_insee.py --download')
        return 2
    try:
        with sqlite3.connect(f'file:{path.resolve()}?mode=ro', uri=True) as db:
            db.row_factory = sqlite3.Row
            tables = {r[0] for r in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}
            if not {'communes', 'metadata'}.issubset(tables):
                print('ERREUR : mauvaise base SQLite (communes/metadata absentes).')
                return 2
            n = db.execute('SELECT COUNT(*) FROM communes').fetchone()[0]
            year = db.execute("SELECT value FROM metadata WHERE key='vintage'").fetchone()
            print(f'Référentiel INSEE : {n:,} communes ; millésime {year[0] if year else "?"}')
            if 'postal_codes' not in tables:
                print('ERREUR : table postal_codes absente. Le code 76600 risque de pointer sur Saint-Léonard.')
                print('Commande : python3 tools/import_codes_postaux.py --download')
                return 2
            m = db.execute('SELECT COUNT(*) FROM postal_codes').fetchone()[0]
            print(f'Codes postaux : {m:,} correspondances')
            expected = {'66300': ('66134', 'Passa'), '76600': ('76351', 'Le Havre'), '76610': ('76351', 'Le Havre')}
            problems = []
            for postal, (insee, name) in expected.items():
                rows = db.execute('''SELECT c.code, c.name FROM postal_codes p
                    JOIN communes c ON c.code=p.code WHERE p.postal_code=? ORDER BY c.name''', (postal,)).fetchall()
                print(f'CP {postal} : {len(rows)} commune(s) : '
                      + ', '.join(r['name'] for r in rows[:8]) + ('…' if len(rows)>8 else ''))
                if insee not in {r['code'] for r in rows}:
                    problems.append(f'{postal} devrait inclure {name} ({insee})')
            if m < 30000:
                problems.append('import postal incomplet')
            if problems:
                print('ERREUR : ' + ' ; '.join(problems))
                print('Réimporter le CSV La Poste : python3 tools/import_codes_postaux.py --download')
                return 1
            print('OK — 66300, 76600, 76610 : correspondances postales valides.')
            return 0
    except sqlite3.DatabaseError as error:
        print('ERREUR SQLite :', error)
        return 2


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--db', type=Path, default=DEFAULT)
    args = parser.parse_args()
    sys.exit(check(args.db))
