#!/usr/bin/env python3
"""Version and distribute DECP snapshots without putting multi-GB files in Git."""
from __future__ import annotations

import argparse
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import re
import sqlite3
import subprocess
import sys
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
REPO = "PsiGuardian/Populorum"
FORMAT = "populorum-decp-archive-v1"
PART_BYTES = 256 * 1024 * 1024
SCHEMA = "populorum-decp-1"
NAME = re.compile(r"decp-[0-9]{4}-[0-9]{2}\.json\Z")
TAG = re.compile(r"[A-Za-z0-9][A-Za-z0-9_.-]{0,79}\Z")
SHA = re.compile(r"[0-9a-f]{64}\Z")


def digest(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for block in iter(lambda: fh.read(1024 * 1024), b""):
            h.update(block)
    return h.hexdigest()


def check_index(path: Path) -> list[tuple[str, str]]:
    if not path.is_file():
        raise ValueError("Index SQLite absent : " + str(path))
    with sqlite3.connect(path.as_uri() + "?mode=ro", uri=True) as db:
        db.execute("PRAGMA query_only=ON")
        tables = {r[0] for r in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}
        required = {"metadata", "imports", "contracts", "contract_sources", "contract_publications"}
        if not required.issubset(tables):
            raise ValueError("Index incomplet : " + str(sorted(required - tables)))
        if db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone() != (SCHEMA,):
            raise ValueError("Version du schéma DECP inconnue")
        if db.execute("PRAGMA quick_check").fetchone()[0] != "ok":
            raise ValueError("Échec du contrôle d'intégrité SQLite")
        sources = db.execute("SELECT filename, sha256 FROM imports ORDER BY filename,sha256").fetchall()
    for name, sha in sources:
        if not NAME.fullmatch(name) or not SHA.fullmatch(sha):
            raise ValueError("Nom ou empreinte source non conforme : " + str(name))
    return sources


def pack_file(source: Path, output: Path) -> dict:
    if not source.is_file() or source.is_symlink():
        raise ValueError("Fichier source absent ou lien symbolique refusé : " + str(source))
    entry = {"name": source.name, "bytes": source.stat().st_size, "sha256": digest(source), "parts": []}
    process = subprocess.Popen(["zstd", "-q", "-T0", "-9", "-c", str(source)],
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    try:
        index = 0
        while True:
            name = f"{source.name}.zst.part-{index:04d}"
            part = output / name
            written = 0
            with part.open("xb") as handle:
                while written < PART_BYTES:
                    block = process.stdout.read(min(1024 * 1024, PART_BYTES - written))
                    if not block:
                        break
                    handle.write(block)
                    written += len(block)
            if not written:
                part.unlink()
                break
            entry["parts"].append({"name": name, "bytes": written, "sha256": digest(part)})
            index += 1
        error = process.stderr.read().decode("utf-8", "replace")
        if process.wait() != 0:
            raise ValueError("Compression zstd échouée : " + error.strip())
    finally:
        if process.poll() is None:
            process.kill()
            process.wait()
    return entry


def package(args: argparse.Namespace) -> None:
    db_path = args.db.resolve()
    expected = check_index(db_path)
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=True)
    if any(output.iterdir()):
        raise ValueError("Utiliser un dossier de sortie vide (évite les mélanges de versions).")
    items = []
    if not args.index_only:
        seen = set()
        for filename, sha in expected:
            if filename in seen:
                raise ValueError("Même nom de fichier associé à plusieurs empreintes : " + filename)
            seen.add(filename)
            source = args.source_dir / filename
            if not source.is_file() or digest(source) != sha:
                raise ValueError("Source absente ou SHA-256 divergent : " + filename)
            items.append((source, sha))
    # SQLite backup API creates a transactionally consistent snapshot, including WAL.
    # Never compress the live file in place while Symfony may be reading it.
    staging = output / ".staging"
    staging.mkdir()
    snapshot = staging / "public-procurement.sqlite"
    try:
        with sqlite3.connect(db_path.as_uri() + "?mode=ro", uri=True) as reader:
            with sqlite3.connect(snapshot) as writer:
                reader.backup(writer, pages=2000, sleep=0.1)
        check_index(snapshot)
        index = pack_file(snapshot, output)
    finally:
        snapshot.unlink(missing_ok=True)
        staging.rmdir()
    archived = []
    for number, (path, sha) in enumerate(items, 1):
        print(f"Source {number}/{len(items)} : {path.name}", flush=True)
        item = pack_file(path, output)
        if item["sha256"] != sha:
            raise ValueError("Source modifiée pendant l'archivage : " + path.name)
        archived.append(item)
    manifest = {
        "format": FORMAT,
        "created_at_utc": datetime.now(timezone.utc).isoformat(),
        "origin": "https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides/",
        "schema": SCHEMA,
        "sources_in_database": len(expected),
        "sources_in_archive": len(archived),
        "index": index,
        "sources": archived,
    }
    (output / "MANIFEST.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    with (output / "SHA256SUMS.txt").open("w", encoding="utf-8") as checks:
        for file in sorted(output.iterdir()):
            if file.name != "SHA256SUMS.txt":
                checks.write(digest(file) + "  " + file.name + "\n")
    print(f"Archive prête : {output} ; {len(archived)} sources ; fichiers de moins de 256 Mio.")


def validate_entry(entry: dict) -> None:
    if not isinstance(entry, dict) or not SHA.fullmatch(str(entry.get("sha256", ""))):
        raise ValueError("Empreinte d'archive invalide")
    if not isinstance(entry.get("bytes"), int) or entry["bytes"] < 0:
        raise ValueError("Taille de fichier invalide")
    if not entry.get("parts") or not isinstance(entry["parts"], list):
        raise ValueError("Archive sans parties")
    if not all(isinstance(p.get("bytes"), int) and 0 < p["bytes"] <= PART_BYTES
               and SHA.fullmatch(str(p.get("sha256", "")))
               and re.fullmatch(r"[a-zA-Z0-9_.-]+\.zst\.part-[0-9]{4}", str(p.get("name", "")))
               for p in entry["parts"]):
        raise ValueError("Parties non conformes")


def download(url: str, output: Path, expected: str | None = None) -> None:
    if output.is_file() and (expected is None or digest(output) == expected):
        return
    if output.exists() and not output.is_file():
        raise ValueError("Destination non régulière : " + str(output))
    temporary = output.with_name(output.name + ".download")
    temporary.unlink(missing_ok=True)
    try:
        with urllib.request.urlopen(urllib.request.Request(
            url, headers={"User-Agent": "Populorum-DECP-Archive/1.0"}
        ), timeout=90) as remote:
            if urllib.parse.urlparse(remote.url).scheme != "https":
                raise ValueError("Redirection non HTTPS refusée")
            with temporary.open("xb") as target:
                for chunk in iter(lambda: remote.read(1024 * 1024), b""):
                    target.write(chunk)
        if expected is not None and digest(temporary) != expected:
            raise ValueError("SHA-256 incorrect : " + output.name)
        os.replace(temporary, output)
    finally:
        temporary.unlink(missing_ok=True)


def restore_entry(entry: dict, target: Path, cache: Path, url_prefix: str) -> None:
    validate_entry(entry)
    if target.exists():
        if target.is_file() and target.stat().st_size == entry["bytes"] and digest(target) == entry["sha256"]:
            print("Déjà présent et vérifié :", target)
            return
        raise ValueError("Destination existante différente : " + str(target) + " (aucun écrasement)")
    target.parent.mkdir(parents=True, exist_ok=True)
    parts = []
    for part in entry["parts"]:
        file = cache / part["name"]
        download(url_prefix + urllib.parse.quote(part["name"]), file, part["sha256"])
        if file.stat().st_size != part["bytes"]:
            raise ValueError("Taille de partie divergente : " + file.name)
        parts.append(file)
    temporary = target.with_name(target.name + ".restoring")
    if temporary.exists():
        raise ValueError("Restauration précédente incomplète : " + str(temporary))
    try:
        with temporary.open("xb") as result:
            proc = subprocess.Popen(["zstd", "-q", "-d", "-c"],
                                    stdin=subprocess.PIPE, stdout=result, stderr=subprocess.PIPE)
            try:
                for file in parts:
                    with file.open("rb") as handle:
                        for block in iter(lambda: handle.read(1024 * 1024), b""):
                            proc.stdin.write(block)
                proc.stdin.close()
                error = proc.stderr.read().decode("utf-8", "replace")
                if proc.wait() != 0:
                    raise ValueError("Décompression échouée : " + error.strip())
            finally:
                if proc.poll() is None:
                    proc.kill()
                    proc.wait()
        if temporary.stat().st_size != entry["bytes"] or digest(temporary) != entry["sha256"]:
            raise ValueError("SHA-256 ou taille de la base décompressée incorrecte")
        if target.name == "public-procurement.sqlite":
            check_index(temporary)
        os.replace(temporary, target)
        print("Installé et vérifié :", target)
    finally:
        temporary.unlink(missing_ok=True)


def install(args: argparse.Namespace) -> None:
    if not TAG.fullmatch(args.tag):
        raise ValueError("Tag de release invalide")
    if not shutil_which_zstd():
        raise ValueError("zstd absent : sudo pacman -S --needed zstd")
    prefix = f"https://github.com/{REPO}/releases/download/{urllib.parse.quote(args.tag)}/"
    cache = (ROOT / "var" / "decp-archive-cache" / args.tag).resolve()
    cache.mkdir(parents=True, exist_ok=True)
    manifest_path = cache / "MANIFEST.json"
    download(prefix + "MANIFEST.json", manifest_path)
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("format") != FORMAT or manifest.get("schema") != SCHEMA:
        raise ValueError("Format de manifeste non reconnu")
    index = manifest["index"]
    if index.get("name") != "public-procurement.sqlite":
        raise ValueError("Le manifeste ne décrit pas l'index attendu")
    sources = manifest["sources"]
    if not args.index_only and (
        manifest.get("sources_in_archive") != manifest.get("sources_in_database")
        or len(sources) != manifest["sources_in_archive"]
    ):
        raise ValueError("Archive sans les sources complètes : demander --index-only ou une release complète")
    restore_entry(index, ROOT / "data/research/public-procurement.sqlite", cache, prefix)
    if not args.index_only:
        for entry in sources:
            if not NAME.fullmatch(entry.get("name", "")):
                raise ValueError("Nom de source non conforme")
            restore_entry(entry, ROOT / "data/research/decp-sources" / entry["name"], cache, prefix)


def shutil_which_zstd() -> bool:
    import shutil
    return shutil.which("zstd") is not None


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    subs = parser.add_subparsers(dest="command", required=True)
    p = subs.add_parser("package", help="Compresser et fractionner la base et les sources locales")
    p.add_argument("--db", type=Path, default=ROOT / "data/research/public-procurement.sqlite")
    p.add_argument("--source-dir", type=Path, default=ROOT / "data/research/decp-sources")
    p.add_argument("--output", type=Path, required=True)
    p.add_argument("--index-only", action="store_true")
    q = subs.add_parser("install", help="Installer une archive publiée sur les Releases")
    q.add_argument("--tag", required=True)
    q.add_argument("--index-only", action="store_true")
    args = parser.parse_args()
    if not shutil_which_zstd():
        parser.error("zstd absent : sudo pacman -S --needed zstd")
    try:
        package(args) if args.command == "package" else install(args)
    except (ValueError, OSError, sqlite3.Error, subprocess.SubprocessError) as exc:
        parser.exit(1, f"ÉCHEC : {exc}\n")


if __name__ == "__main__":
    main()
