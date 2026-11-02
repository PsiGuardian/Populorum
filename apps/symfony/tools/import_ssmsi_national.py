#!/usr/bin/env python3
"""Index the official SSMSI municipal CSV.gz (2026 geography), keeping suppressed values NULL."""
from __future__ import annotations
import argparse
import csv
import gzip
import hashlib
from pathlib import Path
import sqlite3

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUTPUT = ROOT / "data/research/ssmsi-national-2025.sqlite"
SOURCE = "https://www.data.gouv.fr/datasets/bases-statistiques-communale-departementale-et-regionale-de-la-delinquance-enregistree-par-la-police-et-la-gendarmerie-nationales"


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as reader:
        for chunk in iter(lambda: reader.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def numeric(value: str, integer: bool = False):
    value = (value or "").strip().replace(",", ".")
    if value in ("", "NA", "ndiff", "N/A"):
        return None
    try:
        return int(value) if integer else float(value)
    except ValueError:
        raise ValueError("Invalid numeric field in official SSMSI source: " + value[:80])


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, required=True, help="Official SSMSI CSV.gz file")
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    args = parser.parse_args()
    source, target = args.source.resolve(), args.output.resolve()
    if not source.is_file() or source.is_symlink():
        parser.error("Source file missing or a symlink")
    if target.exists() or target.is_symlink():
        parser.error("Target already exists: no overwrite. Backup and move it explicitly.")
    geography = ROOT / "data/geography/communes-2026.sqlite"
    if not geography.is_file():
        parser.error("INSEE index missing: run php bin/console app:geography:install --env=demo")
    with sqlite3.connect(geography.resolve().as_uri() + "?mode=ro", uri=True) as db:
        allowed = {r[0] for r in db.execute("SELECT code FROM communes")}
    digest = sha256(source)
    target.parent.mkdir(parents=True, exist_ok=True)
    temporary = target.with_name("." + target.name + ".importing")
    if temporary.exists():
        parser.error("Unfinished import present: " + str(temporary))
    try:
        with sqlite3.connect(temporary) as db:
            db.executescript("""
                PRAGMA journal_mode=DELETE;
                CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL);
                CREATE TABLE facts (
                    code TEXT NOT NULL,
                    year INTEGER NOT NULL,
                    indicator TEXT NOT NULL,
                    status TEXT NOT NULL,
                    rate REAL,
                    count INTEGER,
                    count_unit TEXT NOT NULL,
                    population_vintage TEXT,
                    housing_vintage TEXT,
                    PRIMARY KEY(code, year, indicator)
                ) WITHOUT ROWID;
            """)
            with gzip.open(source, "rt", encoding="utf-8-sig", newline="") as handle:
                reader = csv.DictReader(handle, delimiter=";")
                columns = reader.fieldnames or []
                required = {"CODGEO_2026", "annee", "indicateur", "nombre",
                            "taux_pour_mille", "est_diffuse", "unite_de_compte"}
                if not required.issubset(columns):
                    raise ValueError("Expected official 2026 geography SSMSI columns; found " + repr(columns))
                pending = []
                accepted = 0
                for row in reader:
                    code = (row["CODGEO_2026"] or "").strip().upper()
                    if code not in allowed:
                        continue  # municipal arrondissements not in the INSEE municipality reference
                    year_str = (row["annee"] or "").strip()
                    if year_str not in ("2024", "2025"):
                        continue
                    indicator = (row["indicateur"] or "").strip()
                    if not indicator:
                        raise ValueError("Missing indicator")
                    status = (row["est_diffuse"] or "").strip()
                    published = status == "diff"
                    pending.append((
                        code, int(year_str), indicator, status,
                        numeric(row["taux_pour_mille"]) if published else None,
                        numeric(row["nombre"], integer=True) if published else None,
                        (row["unite_de_compte"] or "").strip(),
                        row.get("insee_pop_millesime", ""),
                        row.get("insee_log_millesime", ""),
                    ))
                    if len(pending) >= 5000:
                        db.executemany("INSERT OR REPLACE INTO facts VALUES (?,?,?,?,?,?,?,?,?)", pending)
                        accepted += len(pending)
                        pending.clear()
                if pending:
                    db.executemany("INSERT OR REPLACE INTO facts VALUES (?,?,?,?,?,?,?,?,?)", pending)
                    accepted += len(pending)
            if accepted < 300000:
                raise ValueError("Unexpectedly few SSMSI rows; reject incomplete national source.")
            db.execute("CREATE INDEX idx_facts_indicator_year ON facts (indicator, year, code)")
            coverage = db.execute("SELECT COUNT(DISTINCT code) FROM facts WHERE year=2025 AND status='diff' AND rate IS NOT NULL").fetchone()[0]
            metadata = {
                "schema": "populorum-ssmsi-national-1",
                "geography": "INSEE 2026",
                "years": "2024,2025",
                "source_url": SOURCE,
                "source_sha256": digest,
                "source_filename": source.name,
                "communes_with_published_2025": str(coverage),
                "indexed_rows": str(accepted),
            }
            db.executemany("INSERT INTO metadata VALUES (?,?)", metadata.items())
            db.commit()
            if db.execute("PRAGMA quick_check").fetchone()[0] != "ok":
                raise ValueError("SQLite quick_check failed")
        temporary.rename(target)
        print(f"SSMSI indexed: {accepted} rows, {coverage} communes with at least one published 2025 value.")
        print("Source SHA-256:", digest)
        print("SQLite:", target)
    finally:
        temporary.unlink(missing_ok=True)


if __name__ == "__main__":
    main()
