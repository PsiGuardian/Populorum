#!/usr/bin/env python3
"""Restaure prudemment l'index DECP d'une archive .xz déjà téléchargée.

N'écrase jamais une base valide par défaut. Une base incorrecte est sauvegardée.
La restauration se fait par fichier temporaire contrôlé, puis remplacement local.
"""
from __future__ import annotations

import argparse
from datetime import datetime, timezone
import lzma
import os
from pathlib import Path
import shutil
import sys
import tempfile
from uuid import uuid4

from decp_index_health import IndexErrorDetail, inspect_database

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DB = ROOT / 'data/research/public-procurement.sqlite'
MAX_UNPACKED_BYTES = 512 * 1024 * 1024


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', type=Path, help='Archive officielle .sqlite.xz de la V7')
    parser.add_argument('--db', type=Path, default=DEFAULT_DB, help='Index à installer')
    parser.add_argument('--replace-valid', action='store_true', help='Autoriser remplacement d’un index valide (sauvegardé)')
    args = parser.parse_args()
    archive = args.archive.expanduser().resolve()
    target = args.db.expanduser().resolve()
    if not archive.is_file() or archive.suffix.lower() != '.xz':
        print(f'ÉCHEC — Archive XZ introuvable ou non reconnue : {archive}', file=sys.stderr)
        return 1

    if target.exists():
        try:
            old = inspect_database(target, check_links=False)
            if not args.replace_valid:
                print(f'Index déjà valide : {target} ({old["counts"]["contracts"]:,} contrats).')
                print('Aucun remplacement. Pour forcer, utiliser --replace-valid (sauvegarde automatique).')
                return 0
        except (IndexErrorDetail, OSError) as e:
            print('Base actuellement incorrecte :', e)
    target.parent.mkdir(parents=True, exist_ok=True)
    tmp_path = None
    backup = None
    sidecar_backups = []
    try:
        # Decompress first, without modifying or removing the existing database.
        fd, raw = tempfile.mkstemp(prefix='.public-procurement-check-', suffix='.sqlite', dir=target.parent)
        tmp_path = Path(raw)
        length = 0
        with os.fdopen(fd, 'wb') as dest, lzma.open(archive, 'rb') as source:
            while chunk := source.read(1024 * 1024):
                length += len(chunk)
                if length > MAX_UNPACKED_BYTES:
                    raise ValueError('Archive trop grande après décompression (limite : 512 Mio)')
                dest.write(chunk)
            dest.flush()
            os.fsync(dest.fileno())
        result = inspect_database(tmp_path)
        # Verifying a WAL-mode SQLite file can leave zero-length auxiliary files.
        for suffix in ('-wal', '-shm'):
            Path(str(tmp_path) + suffix).unlink(missing_ok=True)
        print(f'Archive contrôlée : {result["counts"]["contracts"]:,} contrats ; intégrité OK.')
        if target.exists():
            stamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
            backup = target.with_name(target.name + f'.backup-{stamp}-{uuid4().hex[:8]}')
            os.replace(target, backup)
            print('Ancienne base sauvegardée :', backup)
        # Preserve WAL/SHM sidecars too: an old WAL must never be applied to a new DB.
        for suffix in ('-wal', '-shm'):
            sidecar = Path(str(target) + suffix)
            if sidecar.exists():
                saved = Path(str(backup or target.with_name(target.name + '.orphan-' + uuid4().hex[:8])) + suffix)
                os.replace(sidecar, saved)
                sidecar_backups.append((sidecar, saved))
                print('Fichier annexe sauvegardé :', saved)
        try:
            os.chmod(tmp_path, 0o644)
            os.replace(tmp_path, target)
            tmp_path = None
        except OSError:
            # Restore original if promoting new DB failed.
            if backup is not None and not target.exists():
                os.replace(backup, target)
            for original, saved in reversed(sidecar_backups):
                if saved.exists() and not original.exists():
                    os.replace(saved, original)
            raise
        print('OK — Index restauré :', target)
        print('Relancer : python3 tools/verify_decp_v7.py')
        return 0
    except (OSError, ValueError, lzma.LZMAError, IndexErrorDetail) as e:
        print(f'ÉCHEC — Restauration annulée : {e}', file=sys.stderr)
        return 1
    finally:
        if tmp_path is not None:
            tmp_path.unlink(missing_ok=True)
            for suffix in ('-wal', '-shm'):
                Path(str(tmp_path) + suffix).unlink(missing_ok=True)


if __name__ == '__main__':
    sys.exit(main())
