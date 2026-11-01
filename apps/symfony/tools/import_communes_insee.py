#!/usr/bin/env python3
"""Import the official INSEE COG 2026 into a *separate*, read-only geography index.

No modification of Doctrine's operational DB or fictive simulation.
Use --download to fetch public INSEE CSVs, or supply the three CSVs offline.
"""
from __future__ import annotations
import argparse
import csv
import hashlib
import io
import os
from pathlib import Path
import re
import sqlite3
import sys
import unicodedata
from zipfile import ZipFile
from urllib.request import Request, urlopen

BASE = 'https://www.insee.fr/fr/statistiques/fichier/8740222/'
FILES = {'communes': 'v_commune_2026.csv', 'departments': 'v_departement_2026.csv', 'regions': 'v_region_2026.csv'}
ROOT = Path(__file__).resolve().parents[1]
DEFAULT = ROOT / 'data/geography/communes-2026.sqlite'
SCHEMA = '''
CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE communes (
 code TEXT PRIMARY KEY,
 name TEXT NOT NULL,
 search_name TEXT NOT NULL,
 department_code TEXT NOT NULL,
 department_name TEXT NOT NULL,
 region_code TEXT NOT NULL,
 region_name TEXT NOT NULL
);
CREATE INDEX communes_search ON communes(search_name);
CREATE INDEX communes_department ON communes(region_code, department_code, name);
CREATE INDEX communes_name ON communes(name);
'''


def normalized(text: str) -> str:
    return ''.join(x for x in unicodedata.normalize('NFKD', text.casefold().replace('œ', 'oe').replace('æ', 'ae')) if not unicodedata.combining(x))


def download(name: str) -> bytes:
    url = BASE + FILES[name]
    request = Request(url, headers={'User-Agent': 'Populorum-COG-Importer/1.0 (public-data)'})
    with urlopen(request, timeout=45) as response:
        content = response.read(10_000_001)
    if len(content) > 10_000_000:
        raise ValueError('Fichier INSEE anormalement volumineux : ' + name)
    return content


def csv_rows(content: bytes):
    text = content.decode('utf-8-sig')
    reader = csv.DictReader(io.StringIO(text, newline=''))
    if not reader.fieldnames or 'LIBELLE' not in reader.fieldnames:
        raise ValueError('CSV INSEE non reconnu (colonne LIBELLE absente)')
    return list(reader)


def run(inputs: dict[str, bytes], target: Path, minimum: int) -> int:
    sources = {name: csv_rows(content) for name, content in inputs.items()}
    regions = {row['REG'].strip(): row['LIBELLE'].strip() for row in sources['regions']}
    departments = {row['DEP'].strip(): row['LIBELLE'].strip() for row in sources['departments']}
    temp = target.with_name(target.name + '.temporary')
    target.parent.mkdir(parents=True, exist_ok=True)
    if temp.exists():
        temp.unlink()
    db = sqlite3.connect(str(temp))
    try:
        db.executescript(SCHEMA)
        number = 0
        skipped = 0
        seen: set[str] = set()
        for row in sources['communes']:
            if row.get('TYPECOM', '').strip() != 'COM':
                skipped += 1  # arrondissements municipaux and delegated/associated communes
                continue
            code = row['COM'].strip().upper()
            region_code = row['REG'].strip()
            department_code = row['DEP'].strip().upper()
            name = row['LIBELLE'].strip()
            if not re.fullmatch(r'(?:\d{5}|2[AB]\d{3})', code):
                raise ValueError('Code INSEE non reconnu : ' + code)
            if not name or code in seen:
                raise ValueError('Nom vide ou commune dupliquée : ' + code)
            if region_code not in regions or department_code not in departments:
                raise ValueError('Région ou département non référencé : ' + code)
            seen.add(code)
            db.execute('INSERT INTO communes VALUES (?,?,?,?,?,?,?)', (
                code, name, normalized(name), department_code, departments[department_code], region_code, regions[region_code],
            ))
            number += 1
        if number < minimum:
            raise ValueError(f'Import incomplet : {number} communes (minimum attendu : {minimum}).')
        values = {
            'vintage': '2026', 'publisher': 'INSEE', 'source': 'https://www.insee.fr/fr/information/8740222',
            'type': 'communes_actuelles_TYPECOM_COM', 'count': str(number), 'excluded_other_types': str(skipped),
        }
        for name, content in inputs.items():
            values['sha256_' + name] = hashlib.sha256(content).hexdigest()
        db.executemany('INSERT INTO metadata VALUES (?,?)', values.items())
        db.commit()
        result = db.execute('PRAGMA integrity_check').fetchone()[0]
        if result != 'ok':
            raise ValueError('Index SQLite invalide : ' + result)
    except BaseException:
        db.close()
        temp.unlink(missing_ok=True)
        raise
    db.close()
    os.replace(temp, target)
    print(f'OK : {number} communes de référence INSEE 2026 ; {skipped} autres types non assimilés à des communes.')
    print('Index :', target)
    print('Couverture géographique ≠ couverture financière. Aucun montant n’a été inventé.')
    return number


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--download', action='store_true', help='Télécharger les trois CSV officiels sur insee.fr')
    parser.add_argument('--zip', type=Path, help='Archive officielle cog_ensemble_2026_csv.zip, téléchargement manuel')
    parser.add_argument('--communes', type=Path, help='v_commune_2026.csv local')
    parser.add_argument('--departements', type=Path, help='v_departement_2026.csv local')
    parser.add_argument('--regions', type=Path, help='v_region_2026.csv local')
    parser.add_argument('--output', type=Path, default=DEFAULT)
    parser.add_argument('--min-communes', type=int, default=30000)
    args = parser.parse_args()
    if not args.download and not args.zip and not (args.communes and args.departements and args.regions):
        parser.error('Choisissez --download, --zip ou les trois fichiers --communes, --departements, --regions.')
    try:
        if args.download:
            inputs = {name: download(name) for name in FILES}
        elif args.zip:
            with ZipFile(args.zip) as archive:
                files = {Path(item).name.lower(): item for item in archive.namelist()}
                inputs = {}
                for name, filename in FILES.items():
                    actual = files.get(filename.lower())
                    if not actual:
                        raise ValueError('Fichier absent de l’archive INSEE : ' + filename)
                    if archive.getinfo(actual).file_size > 10_000_000:
                        raise ValueError('Fichier anormalement volumineux : ' + filename)
                    inputs[name] = archive.read(actual)
        else:
            inputs = {name: path.read_bytes() for name, path in (
                ('communes', args.communes), ('departments', args.departements), ('regions', args.regions),
            )}
        run(inputs, args.output, args.min_communes)
    except (OSError, ValueError, UnicodeError, KeyError) as exc:
        sys.exit(f'ÉCHEC : {exc}\nConsultez https://www.insee.fr/fr/information/8740222 pour le téléchargement manuel des CSV.')


if __name__ == '__main__':
    main()
