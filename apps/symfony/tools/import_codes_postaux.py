#!/usr/bin/env python3
"""La Poste official postal-code / INSEE crosswalk, imported into the shared geographic index.

Recommended: python3 tools/import_codes_postaux.py --download
Offline:     python3 tools/import_codes_postaux.py ~/Téléchargements/laposte-hexasmal.csv

No new table of communes; no modification of the Doctrine simulation database.
Sources:
  https://www.data.gouv.fr/datasets/base-officielle-des-codes-postaux
  https://www.data.gouv.fr/api/1/datasets/r/008a2dda-2c60-4b63-b910-998f6f818089
"""
from __future__ import annotations
import argparse
import csv
import hashlib
import io
import unicodedata
from pathlib import Path
import sqlite3
import sys
import tempfile
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[1]
URLS = [
    'https://www.data.gouv.fr/api/1/datasets/r/008a2dda-2c60-4b63-b910-998f6f818089',
    'https://datanova.laposte.fr/data-fair/api/v1/datasets/laposte-hexasmal/metadata-attachments/base-officielle-codes-postaux.csv',
]


def download_csv() -> tuple[bytes, str]:
    errors = []
    for url in URLS:
        try:
            request = Request(url, headers={'User-Agent': 'Demos-Kratos-Public-Data/1.0'})
            with urlopen(request, timeout=50) as response:
                if not response.url.startswith('https://'):
                    raise ValueError('Redirection non HTTPS')
                content = response.read(12_000_001)
            if not (500_000 <= len(content) <= 12_000_000):
                raise ValueError('Taille inattendue, source rejetée')
            if b'Code_commune_INSEE' not in content[:2048] and b'code_commune_insee' not in content[:2048]:
                raise ValueError('En-tête du CSV La Poste non reconnu')
            return content, url
        except (OSError, ValueError) as exc:
            errors.append(f'{url}: {exc}')
    raise ValueError('Téléchargement officiel indisponible ; utilisez le CSV téléchargé manuellement.\n' + '\n'.join(errors))


def decode_csv(raw: bytes) -> tuple[str, str]:
    """La Poste exports may use legacy Western-European encoding (CP1252).

    Try Unicode first; never use errors="ignore" or "replace" to avoid
    silently corrupting commune codes, headers or names.
    """
    if not raw:
        raise ValueError('Fichier CSV postal vide.')
    if len(raw) > 20_000_000:
        raise ValueError('CSV postal trop volumineux (limite 20 Mo).')
    for charset in ('utf-8-sig', 'cp1252', 'iso-8859-1'):
        try:
            content = raw.decode(charset, errors='strict')
        except UnicodeDecodeError:
            continue
        head = content.splitlines()[0].lstrip('\ufeff') if content.splitlines() else ''
        if 'code_commune_insee' in head.casefold() and 'code_postal' in head.casefold():
            return content, charset
    raise ValueError('Encodage ou en-tête postal inconnu : Code_commune_INSEE et Code_postal attendus.')


def run(source: Path, destination: Path, source_url: str = '') -> int:
    if not destination.exists():
        raise ValueError('Importez le référentiel INSEE 2026 avant les codes postaux.')
    raw = source.read_bytes()
    decoded, encoding = decode_csv(raw)
    with io.StringIO(decoded, newline='') as handle:
        head = handle.readline()
        handle.seek(0)
        reader = csv.DictReader(handle, delimiter=';' if ';' in head else ',')
        # L'en-tête officiel de La Poste commence par « #Code_commune_INSEE ».
        # Conserver les clés originales pour DictReader, ne normaliser que la recherche.
        def canonical_header(value: str) -> str:
            value = unicodedata.normalize('NFKD', value.lstrip('\ufeff').strip().lstrip('#').strip().casefold())
            return ''.join(ch for ch in value if ch.isalnum() and not unicodedata.combining(ch))

        keys = {canonical_header(key): key for key in (reader.fieldnames or []) if key}
        code_key = next((keys[k] for k in ('codecommuneinsee', 'codeinsee', 'inseecom', 'codecommune') if k in keys), None)
        postal_key = next((keys[k] for k in ('codepostal', 'postalcode') if k in keys), None)
        if not code_key or not postal_key:
            found = ', '.join(repr(v) for v in (reader.fieldnames or [])[:12])
            raise ValueError('En-têtes postaux non reconnus. Colonnes présentes : ' + found)
        # A transactional refresh guarantees failure never destroys a previous working import.
        with sqlite3.connect(destination, timeout=15) as db:
            if not db.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name='communes'").fetchone():
                raise ValueError('Référentiel INSEE absent ou invalide.')
            db.execute('BEGIN IMMEDIATE')
            db.execute('CREATE TABLE IF NOT EXISTS postal_codes (code TEXT NOT NULL, postal_code TEXT NOT NULL, PRIMARY KEY(code, postal_code)) WITHOUT ROWID')
            db.execute('DELETE FROM postal_codes')
            count = 0
            for row in reader:
                code = str(row[code_key] or '').strip().upper()
                postal = str(row[postal_key] or '').strip()
                if len(postal) != 5 or not postal.isdigit():
                    continue
                cur = db.execute('INSERT OR IGNORE INTO postal_codes(code, postal_code) SELECT code, ? FROM communes WHERE code=?', (postal, code))
                count += cur.rowcount
            if count < 30000:
                raise ValueError(f'Correspondances insuffisantes ({count}) ; import annulé sans modifier les anciennes données.')
            db.execute('CREATE INDEX IF NOT EXISTS postal_search ON postal_codes(postal_code)')
            db.execute('INSERT OR REPLACE INTO metadata(key,value) VALUES (?,?)', ('postal_source', source_url or 'La Poste - base officielle des codes postaux'))
            db.execute('INSERT OR REPLACE INTO metadata(key,value) VALUES (?,?)', ('postal_source_sha256', hashlib.sha256(raw).hexdigest()))
            db.execute('INSERT OR REPLACE INTO metadata(key,value) VALUES (?,?)', ('postal_source_encoding', encoding))
    return count


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('csv', nargs='?', type=Path)
    parser.add_argument('--download', action='store_true', help='Télécharger le CSV officiel La Poste via les ressources publiques')
    parser.add_argument('--db', type=Path, default=ROOT / 'data/geography/communes-2026.sqlite')
    args = parser.parse_args()
    if args.download == (args.csv is not None):
        parser.error('Précisez soit --download, soit le chemin du CSV La Poste.')
    try:
        if args.download:
            content, url = download_csv()
            with tempfile.TemporaryDirectory(prefix='populorum-postal-') as temp:
                source = Path(temp) / 'source.csv'
                source.write_bytes(content)
                count = run(source, args.db, url)
        else:
            count = run(args.csv, args.db)
        print(f'OK : {count} correspondances communales code postal → code INSEE importées dans {args.db}')
        print('Recherche par code postal opérationnelle ; les communes et codes INSEE restent ceux du référentiel INSEE.')
    except (ValueError, OSError, sqlite3.DatabaseError, UnicodeError) as exc:
        sys.exit('ÉCHEC : ' + str(exc))
