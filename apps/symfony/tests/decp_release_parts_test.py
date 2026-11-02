#!/usr/bin/env python3
"""No-network regression test for the three-part DECP release format."""
from __future__ import annotations

import argparse
import json
from pathlib import Path
import sqlite3
import sys
import tempfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
from decp_release_parts import split, restore, sha256


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="populorum-decp-parts-") as folder:
        root = Path(folder)
        source = root / "public-procurement.sqlite"
        with sqlite3.connect(source) as db:
            db.executescript("""
                CREATE TABLE metadata (key TEXT PRIMARY KEY,value TEXT NOT NULL);
                CREATE TABLE contracts (contract_key TEXT);
                CREATE TABLE contract_sources (contract_key TEXT);
                CREATE TABLE contract_publications (contract_key TEXT);
                INSERT INTO metadata VALUES ('schema','populorum-decp-1');
                INSERT INTO contracts VALUES ('example');
            """)
        original = sha256(source)
        output = root / "assets"
        split(argparse.Namespace(db=source, output=output))
        manifest = json.loads((output / "public-procurement.parts.json").read_text())
        assert len(manifest["parts"]) == 3
        assert manifest["sha256"] == original
        assert sum(p["bytes"] for p in manifest["parts"]) == source.stat().st_size
        target = root / "restored" / source.name
        restore(argparse.Namespace(tag=None, from_dir=output, target=target))
        assert sha256(target) == original
        restore(argparse.Namespace(tag=None, from_dir=output, target=target))
        corrupt = output / manifest["parts"][1]["name"]
        corrupt.write_bytes(b"tampered")
        try:
            restore(argparse.Namespace(tag=None, from_dir=output, target=root / "other" / source.name))
            raise AssertionError("Corrupted part was accepted")
        except ValueError:
            pass
        assert not (root / "other" / source.name).exists()
        print("OK — DECP three parts: integrity, ordering, reconstruction and corruption rejection")


if __name__ == "__main__":
    main()
