#!/usr/bin/env python3
"""Offline smoke test of public procurement archive packaging and restoration."""
from __future__ import annotations
import argparse
import hashlib
import json
from pathlib import Path
# Tests are separated from operational tools; import only the supported data tooling.
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import sqlite3
import tempfile

from decp_archive import package, restore_entry, digest, check_index


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="populorum-decp-release-test-") as temporary:
        base = Path(temporary)
        db_path = base / "public-procurement.sqlite"
        source_dir = base / "sources"
        source_dir.mkdir()
        source = source_dir / "decp-2026-01.json"
        source.write_text(json.dumps({"marches": [{"objet": "'; DROP TABLE contracts; --"}]}),
                          encoding="utf-8")
        sha = hashlib.sha256(source.read_bytes()).hexdigest()
        with sqlite3.connect(db_path) as db:
            db.executescript("""
                CREATE TABLE metadata (key TEXT PRIMARY KEY,value TEXT NOT NULL);
                CREATE TABLE imports (filename TEXT NOT NULL,sha256 TEXT NOT NULL);
                CREATE TABLE contracts (contract_key TEXT);
                CREATE TABLE contract_sources (contract_key TEXT,source_sha TEXT);
                CREATE TABLE contract_publications (contract_key TEXT,source_sha TEXT);
                CREATE TABLE contract_records (source_sha TEXT,kind TEXT,ordinal INTEGER);
                CREATE TABLE procurement_evidence (evidence_id TEXT);
            """)
            db.execute("INSERT INTO metadata VALUES ('schema','populorum-decp-2')")
            db.execute("INSERT INTO imports VALUES (?,?)", (source.name, sha))
            db.execute("INSERT INTO contracts VALUES (?)", ("contract-1",))
        output = base / "release"
        package(argparse.Namespace(db=db_path, source_dir=source_dir,
                                   output=output, index_only=False))
        manifest = json.loads((output / "MANIFEST.json").read_text(encoding="utf-8"))
        assert manifest["sources_in_database"] == 1
        assert manifest["sources_in_archive"] == 1
        assert manifest["index"]["name"] == "public-procurement.sqlite"
        assert all(p["bytes"] <= 256 * 1024 * 1024
                   for e in [manifest["index"]] + manifest["sources"]
                   for p in e["parts"])
        restored = base / "restored"
        restored.mkdir()
        restore_entry(manifest["index"], restored / "public-procurement.sqlite", output,
                      "https://invalid.example/")
        restore_entry(manifest["sources"][0], restored / source.name, output,
                      "https://invalid.example/")
        assert digest(restored / source.name) == sha
        assert check_index(restored / "public-procurement.sqlite") == [(source.name, sha)]
        restore_entry(manifest["index"], restored / "public-procurement.sqlite", output,
                      "https://invalid.example/")
        with sqlite3.connect(restored / "public-procurement.sqlite") as db:
            assert db.execute("SELECT COUNT(*) FROM contracts").fetchone()[0] == 1
        print("OK — archive DECP : snapshot, manifeste, SHA-256, restauration et idempotence")


if __name__ == "__main__":
    main()
