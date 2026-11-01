#!/usr/bin/env python3
"""Build a separate, read-only index from OFGL consolidated commune CSV (2025).

This importer DOES NOT import central/state spending or personal payment data.
It performs no network requests and cannot overwrite Symfony's operational database.
"""
from __future__ import annotations
import argparse
import csv
from datetime import datetime, timezone
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
import gzip
import hashlib
from pathlib import Path
import re
import sqlite3
import sys

SOURCE = 'https://data.ofgl.fr/explore/dataset/ofgl-base-communes-consolidee/'
SCHEMA = '''
CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE municipalities (
    code TEXT PRIMARY KEY, name TEXT NOT NULL,
    region_code TEXT NOT NULL, region_name TEXT NOT NULL,
    department_code TEXT NOT NULL, department_name TEXT NOT NULL,
    population INTEGER NOT NULL
);
CREATE TABLE aggregates (
    code TEXT NOT NULL, aggregate TEXT NOT NULL,
    amount_cents INTEGER NOT NULL, main_cents INTEGER NOT NULL,
    annex_cents INTEGER NOT NULL, cross_cents INTEGER NOT NULL,
    PRIMARY KEY(code, aggregate), FOREIGN KEY(code) REFERENCES municipalities(code)
);
CREATE INDEX municipality_geo ON municipalities(region_code, department_code, name);
CREATE INDEX municipality_name ON municipalities(name);
CREATE INDEX aggregates_kind ON aggregates(aggregate);
CREATE TABLE national_aggregate (
    aggregate TEXT PRIMARY KEY, amount_cents INTEGER, main_cents INTEGER,
    annex_cents INTEGER, cross_cents INTEGER, represented INTEGER
);
'''


def cents(value: str) -> int:
    value = str(value or '0').strip().replace('\u00a0', '').replace(' ', '')
    if not value:
        return 0
    if ',' in value and '.' in value:
        value = value.replace('.', '').replace(',', '.')
    else:
        value = value.replace(',', '.')
    result = int((Decimal(value) * 100).quantize(Decimal('1'), rounding=ROUND_HALF_UP))
    if abs(result) > 9_000_000_000_000_000_000:
        raise ValueError('montant hors capacité SQLite')
    return result


def run(source: Path, dest: Path, min_cities: int) -> None:
    if not source.is_file():
        raise ValueError(f'CSV introuvable : {source}')
    if source.resolve() == dest.resolve():
        raise ValueError('Source et destination identiques.')
    head = source.open('rb').read(8)
    is_gz = source.suffix == '.gz' or head[:2] == b'\x1f\x8b'
    opener = gzip.open if is_gz else open
    tmp = dest.with_suffix(dest.suffix + '.temporary')
    dest.parent.mkdir(parents=True, exist_ok=True)
    if tmp.exists():
        tmp.unlink()
    db = sqlite3.connect(str(tmp))
    db.execute('PRAGMA journal_mode=DELETE')
    db.execute('PRAGMA foreign_keys=ON')
    try:
        db.executescript(SCHEMA)
        count = 0
        duplicates = 0
        with opener(source, mode='rt', encoding='utf-8-sig', newline='') as handle:
            sample = handle.read(8192)
            handle.seek(0)
            delim = ';' if sample.count(';') > sample.count(',') else ','
            reader = csv.DictReader(handle, delimiter=delim)
            required = {'exer','com_code','com_name','reg_code','dep_code','agregat','montant'}
            if not required.issubset(set(reader.fieldnames or [])):
                raise ValueError(f'Colonnes OFGL manquantes : {sorted(required-set(reader.fieldnames or []))}')
            for line, row in enumerate(reader, 2):
                if str(row.get('exer', '')).strip() != '2025':
                    continue
                code = str(row.get('com_code', '')).strip().upper()
                if not re.fullmatch(r'(?:[0-9]{5}|2[AB][0-9]{3})', code):
                    continue
                name = str(row.get('com_name') or code).strip()[:120]
                agg = str(row.get('agregat', '')).strip()[:150]
                if not name or not agg:
                    continue
                reg_code = str(row.get('reg_code') or '').strip()[:3]
                dep_code = str(row.get('dep_code') or '').strip()[:3]
                reg_name = str(row.get('reg_name') or reg_code).strip()[:100]
                dep_name = str(row.get('dep_name') or dep_code).strip()[:100]
                try:
                    pop = max(0, int(float(str(row.get('ptot') or '0').replace(',', '.'))))
                    amounts = [cents(row.get(k, '0')) for k in ('montant', 'montant_bp', 'montant_ba', 'montant_flux')]
                except (InvalidOperation, ValueError) as exc:
                    raise ValueError(f'Nombre invalide ligne {line} : {exc}') from exc
                db.execute('INSERT INTO municipalities VALUES (?,?,?,?,?,?,?) ON CONFLICT(code) DO UPDATE SET name=excluded.name,region_code=excluded.region_code,region_name=excluded.region_name,department_code=excluded.department_code,department_name=excluded.department_name,population=excluded.population',
                           (code,name,reg_code,reg_name,dep_code,dep_name,pop))
                old = db.execute('SELECT amount_cents, main_cents, annex_cents, cross_cents FROM aggregates WHERE code=? AND aggregate=?', (code,agg)).fetchone()
                if old is not None:
                    if tuple(old) != tuple(amounts):
                        raise ValueError(f'Doublon contradictoire ligne {line} : {code} / {agg} ; vérifiez que le CSV est consolidé, pas par budget.')
                    duplicates += 1
                    continue
                db.execute('INSERT INTO aggregates VALUES (?,?,?,?,?,?)', (code, agg, *amounts))
                count += 1
        cities = db.execute('SELECT COUNT(*) FROM municipalities').fetchone()[0]
        totals = db.execute("SELECT COUNT(*) FROM aggregates WHERE aggregate='Dépenses totales'").fetchone()[0]
        if cities < min_cities:
            raise ValueError(f'Seulement {cities} communes (< {min_cities}). Ne pas confondre extrait partiel et import national.')
        if totals < cities * .9:
            raise ValueError(f'Seulement {totals} communes avec total de dépenses sur {cities}. Import trop lacunaire.')
        db.execute('INSERT INTO national_aggregate SELECT aggregate, SUM(amount_cents), SUM(main_cents), SUM(annex_cents), SUM(cross_cents), COUNT(*) FROM aggregates GROUP BY aggregate')
        sha = hashlib.sha256()
        with source.open('rb') as source_bytes:
            for block in iter(lambda: source_bytes.read(1 << 20), b''):
                sha.update(block)
        meta = dict(year='2025', source=SOURCE, file_sha256=sha.hexdigest(),
                    processed_at=datetime.now(timezone.utc).isoformat(), cities=str(cities), total_lines=str(count),
                    note='Comptes communaux consolidés disponibles. Aucun périmètre État/sécurité sociale. Certaines communes peuvent manquer.')
        db.executemany('INSERT INTO metadata(key,value) VALUES (?,?)', meta.items())
        db.commit()
        db.execute('PRAGMA integrity_check')
        db.close()
        tmp.replace(dest)
        print(f'IMPORT OK : {cities} communes, {totals} totaux de dépenses, {count} agrégats, {duplicates} lignes identiques ignorées.')
        print(f'Index : {dest} ({dest.stat().st_size/1024/1024:.1f} Mio)')
        print('Attention : la somme nationale décrit UNIQUEMENT les communes présentes dans le CSV.')
    except Exception:
        db.close()
        tmp.unlink(missing_ok=True)
        raise


def main():
    parser = argparse.ArgumentParser(description='Indexe un export officiel OFGL consolidé 2025, sans réseau.')
    parser.add_argument('csv', type=Path, help='Export CSV 2025 ofgl-base-communes-consolidee, non le fichier des budgets séparés')
    parser.add_argument('--min-cities', type=int, default=30000, help='Seuil de protection (30000 par défaut, 1 uniquement pour les tests)')
    parser.add_argument('--output', type=Path, default=Path(__file__).resolve().parents[1] / 'data/research/municipal-national-2025.sqlite')
    args = parser.parse_args()
    try:
        run(args.csv, args.output, args.min_cities)
    except (ValueError, OSError, sqlite3.Error) as e:
        print(f'IMPORT REFUSÉ : {e}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
