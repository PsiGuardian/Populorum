#!/usr/bin/env python3
"""Vérifie l'index DECP; message explicite si la mauvaise base est ouverte."""
from __future__ import annotations

import argparse
from pathlib import Path
import sys

from decp_index_health import inspect_database, IndexErrorDetail

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DB = ROOT / 'data/research/public-procurement.sqlite'


def main() -> int:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--db', type=Path, default=DEFAULT_DB, help='Base à contrôler')
    a = p.parse_args()
    try:
        result = inspect_database(a.db)
    except (IndexErrorDetail, OSError) as e:
        print(f'ÉCHEC — {e}', file=sys.stderr)
        print('Le vérificateur ne modifie pas les données.', file=sys.stderr)
        print('Pour restaurer l’index préchargé après sauvegarde :', file=sys.stderr)
        print('  python3 tools/restore_decp_v7_index.py ~/Téléchargements/demos_kratos_decp_v7_index_2026-08.sqlite.xz', file=sys.stderr)
        return 1
    print('OK — Index DECP valide :', result['path'])
    print('Schéma :', result['schema'])
    for name, count in result['counts'].items():
        print(f'  {name}: {count:,}'.replace(',', ' '))
    print('Rappel : montants contractuels, pas paiements effectués.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
