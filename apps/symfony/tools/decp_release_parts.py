#!/usr/bin/env python3
"""Split/reassemble a public DECP SQLite snapshot into exactly three GitHub Release assets."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import sqlite3
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
TAG = re.compile(r"\A[A-Za-z0-9][A-Za-z0-9_.-]{0,79}\Z")
CHUNK = 1024 * 1024
NAME = "public-procurement.sqlite"
MANIFEST = "public-procurement.parts.json"


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(CHUNK), b""):
            h.update(block)
    return h.hexdigest()


def verify_database(path: Path) -> None:
    with sqlite3.connect(path.resolve().as_uri() + "?mode=ro", uri=True) as db:
        db.execute("PRAGMA query_only=ON")
        if db.execute("PRAGMA quick_check").fetchone()[0] != "ok":
            raise ValueError("SQLite quick_check failed")
        if db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone() != ("populorum-decp-1",):
            raise ValueError("DECP schema is not populorum-decp-1")
        for table in ("contracts", "contract_sources", "contract_publications"):
            if db.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", (table,)).fetchone() is None:
                raise ValueError("Missing DECP table: " + table)


def split(args: argparse.Namespace) -> None:
    source = args.db.resolve()
    if source.name != NAME or source.is_symlink():
        raise ValueError("Expected a regular public-procurement.sqlite (not a symlink)")
    verify_database(source)
    # The raw three-part format is used only for a stable, stopped SQLite snapshot.
    wal = source.with_name(source.name + "-wal")
    if wal.exists() and wal.stat().st_size:
        raise ValueError("SQLite WAL is not empty: stop readers/writers, checkpoint before splitting")
    original_size = source.stat().st_size
    original_time = source.stat().st_mtime_ns
    original_sha = sha256(source)
    out = args.output.resolve()
    out.mkdir(parents=True, exist_ok=True)
    if any(out.iterdir()):
        raise ValueError("Output directory must be empty; no overwrite.")
    size = source.stat().st_size
    if size < 3:
        raise ValueError("File too small to split into three parts.")
    sizes = [size // 3 + (i < size % 3) for i in range(3)]
    parts = []
    with source.open("rb") as reader:
        for i, expected in enumerate(sizes):
            name = NAME + f".part-{i:02d}"
            target = out / name
            with target.open("xb") as writer:
                remaining = expected
                while remaining:
                    data = reader.read(min(CHUNK, remaining))
                    if not data:
                        raise ValueError("Unexpected end of SQLite source.")
                    writer.write(data)
                    remaining -= len(data)
            parts.append({"name": name, "bytes": expected, "sha256": sha256(target)})
        if reader.read(1):
            raise ValueError("Source grew during splitting.")
    # Detect any concurrent source change and verify exact byte-for-byte reconstruction.
    if source.stat().st_size != original_size or source.stat().st_mtime_ns != original_time:
        raise ValueError("SQLite changed during the split; discard parts and stop the site")
    digest_parts = hashlib.sha256()
    for part in parts:
        with (out / part["name"]).open("rb") as reader:
            for block in iter(lambda: reader.read(CHUNK), b""):
                digest_parts.update(block)
    if digest_parts.hexdigest() != original_sha or sha256(source) != original_sha:
        raise ValueError("Part reconstruction digest does not match the source")

    manifest = {
        "format": "populorum-decp-raw-three-parts-v1",
        "filename": NAME, "bytes": size, "sha256": original_sha, "parts": parts,
    }
    (out / MANIFEST).write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    with (out / "SHA256SUMS-DECP.txt").open("w", encoding="utf-8") as writer:
        for part in parts:
            writer.write(part["sha256"] + "  " + part["name"] + "\n")
        writer.write(sha256(out / MANIFEST) + "  " + MANIFEST + "\n")
    print("Three parts ready for GitHub Releases:", out)
    print("Upload these 5 assets:", ", ".join([p["name"] for p in parts] + [MANIFEST, "SHA256SUMS-DECP.txt"]))


def read_manifest(path: Path) -> dict:
    obj = json.loads(path.read_text(encoding="utf-8"))
    if obj.get("format") != "populorum-decp-raw-three-parts-v1" or obj.get("filename") != NAME:
        raise ValueError("Unknown DECP manifest format")
    parts = obj.get("parts")
    if not isinstance(parts, list) or len(parts) != 3:
        raise ValueError("Exactly three parts required")
    if [x.get("name") for x in parts] != [NAME + f".part-{i:02d}" for i in range(3)]:
        raise ValueError("Unexpected part names/order")
    if not isinstance(obj.get("bytes"), int) or obj["bytes"] <= 0:
        raise ValueError("Invalid SQLite length")
    if sum(x.get("bytes", -1) for x in parts) != obj["bytes"]:
        raise ValueError("Part sizes do not add up")
    for field in [obj] + parts:
        if not isinstance(field.get("sha256"), str) or not re.fullmatch(r"[0-9a-f]{64}", field["sha256"]):
            raise ValueError("Invalid SHA-256 value")
        if not isinstance(field.get("bytes"), int) or field["bytes"] <= 0:
            raise ValueError("Invalid part size")
    return obj


def download(url: str, destination: Path, checksum: str | None = None) -> None:
    if destination.exists():
        if destination.is_file() and (checksum is None or sha256(destination) == checksum):
            return
        raise ValueError("Existing different file; refusing overwrite: " + str(destination))
    partial = destination.with_name(destination.name + ".incomplete")
    offset = partial.stat().st_size if partial.is_file() else 0
    request = urllib.request.Request(url, headers={
        "User-Agent": "Populorum-DECP/1.0",
        **({"Range": f"bytes={offset}-"} if offset else {}),
    })
    with urllib.request.urlopen(request, timeout=90) as response:
        if response.geturl().split(":", 1)[0] != "https":
            raise ValueError("Non-HTTPS download refused")
        append = offset > 0 and response.status == 206
        with partial.open("ab" if append else "wb") as writer:
            for block in iter(lambda: response.read(CHUNK), b""):
                writer.write(block)
    if checksum is not None and sha256(partial) != checksum:
        raise ValueError("SHA-256 mismatch: " + destination.name + " (incomplete file retained)")
    os.replace(partial, destination)


def restore(args: argparse.Namespace) -> None:
    cache = args.from_dir.resolve() if args.from_dir else ROOT / "var" / "decp-release-cache"
    cache.mkdir(parents=True, exist_ok=True)
    if args.tag:
        if not TAG.fullmatch(args.tag):
            raise ValueError("Invalid release tag")
        prefix = f"https://github.com/PsiGuardian/Populorum/releases/download/{args.tag}/"
        download(prefix + MANIFEST, cache / MANIFEST)
    manifest = read_manifest(cache / MANIFEST)
    destination = args.target.resolve()
    if destination.exists():
        if destination.is_file() and destination.stat().st_size == manifest["bytes"] and sha256(destination) == manifest["sha256"]:
            verify_database(destination)
            print("Existing verified DECP SQLite preserved:", destination)
            return
        raise ValueError("Target already exists, refusing overwrite: " + str(destination))
    destination.parent.mkdir(parents=True, exist_ok=True)
    temporary = destination.with_name(destination.name + ".restoring")
    if temporary.exists():
        raise ValueError("Incomplete restore found; inspect before retrying: " + str(temporary))
    for part in manifest["parts"]:
        if args.tag:
            download(prefix + part["name"], cache / part["name"], part["sha256"])
        file = cache / part["name"]
        if not file.is_file() or file.stat().st_size != part["bytes"] or sha256(file) != part["sha256"]:
            raise ValueError("Missing or invalid part: " + part["name"])
    try:
        with temporary.open("xb") as writer:
            for part in manifest["parts"]:
                with (cache / part["name"]).open("rb") as reader:
                    for block in iter(lambda: reader.read(CHUNK), b""):
                        writer.write(block)
        if temporary.stat().st_size != manifest["bytes"] or sha256(temporary) != manifest["sha256"]:
            raise ValueError("Restored database SHA-256 mismatch")
        verify_database(temporary)
        os.replace(temporary, destination)
        print("DECP SQLite restored and verified:", destination)
    finally:
        temporary.unlink(missing_ok=True)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="cmd", required=True)
    pack = commands.add_parser("split", help="Create exactly three verified raw parts")
    pack.add_argument("--db", type=Path, default=ROOT / "data/research/public-procurement.sqlite")
    pack.add_argument("--output", type=Path, required=True)
    unpack = commands.add_parser("restore", help="Reassemble locally or download from a GitHub Release")
    unpack.add_argument("--tag", default=None)
    unpack.add_argument("--from-dir", type=Path, default=None)
    unpack.add_argument("--target", type=Path, default=ROOT / "data/research/public-procurement.sqlite")
    args = parser.parse_args()
    if args.cmd == "restore" and bool(args.tag) == bool(args.from_dir):
        parser.error("Use either --tag or --from-dir")
    try:
        split(args) if args.cmd == "split" else restore(args)
    except (OSError, ValueError, sqlite3.Error, json.JSONDecodeError) as error:
        parser.exit(1, "ERREUR : " + str(error) + "\n")


if __name__ == "__main__":
    main()
