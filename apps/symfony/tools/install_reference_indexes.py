#!/usr/bin/env python3
"""Restore verified public reference indexes shipped in compressed form.

Never overwrites an existing index; never touches Doctrine's simulation database.
"""
from __future__ import annotations
import argparse
import gzip
import os
from pathlib import Path
import sqlite3
import tempfile

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / 'data/geography/communes-2026.sqlite.gz'
DST = ROOT / 'data/geography/communes-2026.sqlite'


def inspect(path: Path) -> tuple[int, int]:
    with sqlite3.connect(f'file:{path.resolve()}?mode=ro', uri=True) as db:
        if db.execute('PRAGMA quick_check').fetchone()[0] != 'ok':
            raise ValueError('Intégrité SQLite incorrecte')
        tables = {r[0] for r in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}
        if not {'metadata', 'communes', 'postal_codes'} <= tables:
            raise ValueError('Tables INSEE/La Poste manquantes')
        count = db.execute('SELECT COUNT(*) FROM communes').fetchone()[0]
        postals = db.execute('SELECT COUNT(*) FROM postal_codes').fetchone()[0]
        if count < 34000 or postals < 30000:
            raise ValueError(f'Index incomplet : {count} communes ; {postals} associations postales')
        return count, postals


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--check', action='store_true', help='Vérifier le référentiel installé sans modification')
    args = ap.parse_args()
    if DST.exists():
        c, p = inspect(DST)
        print(f'Index existant valide : {c} communes ; {p} correspondances postales. Aucune modification.')
        return
    if args.check:
        raise SystemExit('Index absent : exécuter python3 tools/install_reference_indexes.py')
    if not SRC.is_file():
        raise SystemExit('Archive absente : ' + str(SRC))
    DST.parent.mkdir(parents=True, exist_ok=True)
    fd, tmp = tempfile.mkstemp(prefix='.communes-2026.', suffix='.sqlite', dir=DST.parent)
    try:
        with os.fdopen(fd, 'wb') as output, gzip.open(SRC, 'rb') as packed:
            while True:
                block = packed.read(1024 * 1024)
                if not block:
                    break
                output.write(block)
            output.flush()
            os.fsync(output.fileno())
        c, p = inspect(Path(tmp))
        os.replace(tmp, DST)
        print(f'Index officiel installé : {c} communes ; {p} correspondances postales.')
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)


if __name__ == '__main__':
    try:
        main()
    except (OSError, sqlite3.DatabaseError, ValueError) as exc:
        raise SystemExit('ERREUR : ' + str(exc)) from exc
