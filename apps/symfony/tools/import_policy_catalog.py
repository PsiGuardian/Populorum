#!/usr/bin/env python3
"""Importer uniquement des propositions de loi réellement déposées au Sénat.

La source est l'export CSV officiel du Sénat, fourni explicitement par l'opérateur.
Les programmes électoraux, intentions et propositions fictives ne sont jamais importés.
Les anciens votes et données historiques restent intacts : seul le catalogue officiel
est visible via l'interface.
"""
from __future__ import annotations

import argparse
import csv
from datetime import datetime, timedelta, timezone
import hashlib
import io
import json
from pathlib import Path
import re
import sqlite3
import sys
import unicodedata
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DB = ROOT / "var" / "policy-catalog.sqlite"
SENAT_EXPORT = "https://www.data.gouv.fr/api/1/datasets/r/7698274a-65f6-4687-8b2b-6961199b6922"

SCHEMA = """
CREATE TABLE IF NOT EXISTS policy_measures (
    id TEXT PRIMARY KEY,
    canonical_key TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    category TEXT NOT NULL,
    territory_code TEXT NOT NULL,
    source_type TEXT NOT NULL,
    source_url TEXT NOT NULL,
    source_date TEXT NOT NULL,
    source_reference TEXT NOT NULL,
    origin_actor TEXT NOT NULL,
    legal_status TEXT NOT NULL,
    review_status TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    opened_at TEXT NOT NULL,
    closes_at TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_policy_filters
    ON policy_measures(territory_code, category, closes_at, id);
CREATE TABLE IF NOT EXISTS policy_votes (
    measure_id TEXT NOT NULL REFERENCES policy_measures(id),
    citizen_id TEXT NOT NULL,
    choice TEXT NOT NULL CHECK (choice IN ('pour', 'contre', 'abstention')),
    argument TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL,
    PRIMARY KEY (measure_id, citizen_id)
);
CREATE TABLE IF NOT EXISTS policy_vote_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    measure_id TEXT NOT NULL,
    citizen_id TEXT NOT NULL,
    previous_choice TEXT,
    choice TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
"""

STOPWORDS = re.compile(
    r"\b(proposition\s+de\s+loi|visant\s+[àa]|tendant\s+[àa])\b",
    re.IGNORECASE,
)

def folded(value: str) -> str:
    text = unicodedata.normalize("NFKD", value)
    return "".join(letter for letter in text if not unicodedata.combining(letter)).lower()


def canonical(value: str) -> str:
    value = folded(value)
    value = STOPWORDS.sub(" ", value)
    return " ".join(re.findall(r"[a-z0-9]+", value))


def theme(title: str) -> str:
    s = folded(title)
    categories = [
        ("Retraites", r"retrait|pension|vieillesse"),
        ("Logement", r"logement|habitat|loyer|locat|immobili"),
        ("Santé", r"sante|hopital|medical|medecin|soin|handicap"),
        ("Éducation", r"ecole|enseign|universit|eleve|creche|education"),
        ("Fiscalité", r"taxe|impot|fiscal|cotisation|tva"),
        ("Transports", r"transport|ferrovia|route|circulation|automobile|mobilite"),
        ("Énergie", r"energie|electricit|nucleaire|carburant|hydrogene"),
        ("Agriculture", r"agricul|alimentation|rural|eleveur|paysan"),
        ("Démocratie", r"vote|scrutin|referendum|electoral|constitut|parlement"),
        ("Justice", r"justice|tribunal|penal|crime|delit|prison|police"),
        ("Environnement", r"climat|environnement|biodivers|pollution|dechet"),
        ("Numérique", r"numerique|informatique|internet|intelligence artificielle"),
        ("Travail", r"travail|salaire|emploi|chomage|entreprise"),
        ("Solidarité", r"solidarit|pauvrete|famille|aide sociale"),
        ("Défense", r"defense|militaire|armee"),
    ]
    for label, pattern in categories:
        if re.search(pattern, s):
            return label
    return "Autres politiques publiques"


def field(row: dict[str, str], *tokens: str) -> str:
    for key, value in row.items():
        if not isinstance(key, str):
            continue
        lowered = folded(key).replace("_", " ").replace("-", " ")
        if any(t in lowered for t in tokens):
            return (value or "").strip()
    return ""


def allowed_source(url: str, kind: str) -> bool:
    parsed = urlparse(url.strip())
    if parsed.scheme != "https" or parsed.username or parsed.password:
        return False
    host = (parsed.hostname or "").lower()
    return kind == "proposition_de_loi" and (
        host == "senat.fr" or host.endswith(".senat.fr")
    )


def validated(item: dict) -> dict | None:
    required = (
        "label", "category", "territoryCode", "sourceType",
        "sourceUrl", "sourceDate", "sourceReference",
        "originActor", "legalStatus", "reviewStatus",
    )
    if any(not isinstance(item.get(key), str) or not item[key].strip() for key in required):
        return None
    label = " ".join(item["label"].split())
    if len(label) < 15 or len(label) > 1200:
        return None
    territory = item["territoryCode"]
    if territory != "FR" or item["sourceType"] != "proposition_de_loi":
        return None
    if any(x in folded(item["legalStatus"]) for x in (
        "promulgu", "caduc", "non adopt", "retir", "irrecev", "poursuivi"
    )):
        return None
    if not allowed_source(item["sourceUrl"], item["sourceType"]):
        return None
    # L'identité d'une proposition est son dossier parlementaire, non son
    # intitulé : deux dépôts homonymes restent deux dossiers distincts.
    key = hashlib.sha256(
        ("senat:" + item["sourceUrl"].strip()).encode("utf-8")
    ).hexdigest()
    if not canonical(label):
        return None
    return {
        "id": "pm_" + key[:24], "canonical_key": key, "label": label,
        "category": item["category"], "territory_code": territory,
        "source_type": item["sourceType"], "source_url": item["sourceUrl"],
        "source_date": item["sourceDate"],
        "source_reference": item["sourceReference"],
        "origin_actor": item["originActor"],
        "legal_status": item["legalStatus"],
        "review_status": item["reviewStatus"],
    }


def senate_measures(csv_path: Path) -> list[dict]:
    data = csv_path.read_bytes()
    if len(data) > 50_000_000:
        raise ValueError("Export CSV trop volumineux.")
    try:
        text = data.decode("utf-8-sig")
    except UnicodeDecodeError:
        text = data.decode("cp1252")
    sample = text[:32000]
    try:
        dialect = csv.Sniffer().sniff(sample, delimiters=";\t,")
    except csv.Error:
        dialect = csv.excel
        dialect.delimiter = ";"
    lines = io.StringIO(text)
    reader = csv.DictReader(lines, dialect=dialect)
    if not reader.fieldnames:
        raise ValueError("En-tête CSV absent.")
    names = [folded(name) for name in reader.fieldnames]
    if not any("titre" in n or "intitule" in n for n in names):
        raise ValueError(
            "Colonnes de l'export Sénat non reconnues : " + ", ".join(reader.fieldnames)
        )
    measures: list[dict] = []
    for row in reader:
        title = field(row, "titre", "intitule")
        url = field(row, "url", "lien")
        date = field(row, "date de depot", "date depot", "datedep", "date")
        author = field(row, "auteur")
        legal_status = field(row, "etat", "statut")
        source_reference = field(row, "numero", "num de", "n°", "numdep")
        source_type = field(row, "type de texte", "type", "nature")
        if not title or not url or not date:
            continue
        # Les anciens liens des exports officiels peuvent encore utiliser HTTP.
        # Forcer HTTPS uniquement pour le nom de domaine institutionnel Sénat.
        parsed = urlparse(url)
        if parsed.scheme == "http" and (
            parsed.hostname == "senat.fr"
            or (parsed.hostname or "").endswith(".senat.fr")
        ):
            url = "https://" + (parsed.netloc or "") + (parsed.path or "")
            if parsed.query:
                url += "?" + parsed.query
        # Exclure lois promulguées, propositions caduques, rejetées, retirées
        # et poursuivies dans un autre dossier pour ne présenter que des textes vivants.
        if not legal_status or any(x in folded(legal_status) for x in (
            "promulgu", "caduc", "non adopt", "retir", "irrecev", "poursuivi"
        )):
            continue
        if "proposition de loi" not in folded(source_type):
            continue
        measures.append({
            "label": title,
            "category": theme(title),
            "territoryCode": "FR",
            "sourceType": "proposition_de_loi",
            "sourceUrl": url,
            "sourceDate": date,
            "sourceReference": source_reference or url.rsplit("/", 1)[-1],
            "originActor": author or "Auteur indiqué dans le dossier du Sénat",
            "legalStatus": legal_status or "État à vérifier dans le dossier officiel",
            "reviewStatus": "titre original (non expertisé)",
        })
    # Dans l'export, la date peut être JJ/MM/AAAA ou AAAA-MM-JJ.
    def date_key(row: dict) -> str:
        raw = row["sourceDate"]
        m = re.fullmatch(r"(\d{2})/(\d{2})/(\d{4})", raw)
        return f"{m[3]}-{m[2]}-{m[1]}" if m else raw
    measures.sort(key=date_key, reverse=True)
    return measures


def import_records(db_path: Path, items: list[dict], limit: int = 200, days: int = 90) -> dict:
    if limit < 1 or limit > 10000 or days < 1 or days > 365:
        raise ValueError("Limites autorisées : 1 à 10000 mesures et 1 à 365 jours.")
    unique: dict[str, dict] = {}
    discarded = 0
    for raw in items:
        result = validated(raw)
        if result is None:
            discarded += 1
            continue
        if result["canonical_key"] in unique:
            discarded += 1
            continue
        unique[result["canonical_key"]] = result
        if len(unique) == limit:
            break
    db_path.parent.mkdir(parents=True, exist_ok=True)
    db = sqlite3.connect(db_path, timeout=10)
    try:
        db.execute("PRAGMA foreign_keys = ON")
        db.execute("PRAGMA busy_timeout = 5000")
        db.executescript(SCHEMA)
        # Migration non destructive du catalogue créé avec la toute première version.
        columns = {row[1] for row in db.execute("PRAGMA table_info(policy_measures)")}
        if "status" not in columns:
            db.execute(
                "ALTER TABLE policy_measures ADD COLUMN status TEXT NOT NULL DEFAULT 'open'"
            )
            db.commit()
        start = datetime.now(timezone.utc)
        opened = start.isoformat(timespec="seconds")
        closes = (start + timedelta(days=days)).isoformat(timespec="seconds")
        inserted = 0
        # Compatibilité avec les versions ayant produit un ID depuis le titre :
        # ne pas recopier un même dossier ni réinitialiser ses anciens votes.
        known_urls = {
            row[0] for row in db.execute(
                "SELECT source_url FROM policy_measures WHERE source_type = 'proposition_de_loi'"
            )
        }
        with db:
            for row in unique.values():
                if row["source_url"] in known_urls:
                    continue
                columns = list(row) + ["opened_at", "closes_at", "created_at"]
                values = list(row.values()) + [opened, closes, opened]
                placeholders = ",".join("?" for _ in columns)
                sql = ("INSERT OR IGNORE INTO policy_measures (" + ",".join(columns)
                       + ") VALUES (" + placeholders + ")")
                inserted += db.execute(sql, values).rowcount
        total = db.execute(
            "SELECT COUNT(*) FROM policy_measures WHERE source_type = 'proposition_de_loi'"
        ).fetchone()[0]
    finally:
        db.close()
    return {
        "valid_candidates": len(unique), "rejected_or_duplicate": discarded,
        "inserted": inserted, "database_total": total, "target": limit,
        "review_warning": "Un titre de dossier ne résume pas les articles ; vérifier avant usage réel.",
    }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--senat-csv", type=Path, required=True,
        help="CSV officiel des propositions de loi déposées au Sénat (obligatoire)",
    )
    parser.add_argument("--db", type=Path, default=DEFAULT_DB)
    parser.add_argument("--limit", type=int, default=200)
    parser.add_argument("--days", type=int, default=90)
    args = parser.parse_args(argv)
    items = senate_measures(args.senat_csv)
    if not items:
        parser.error("Aucune proposition de loi en cours trouvée dans l'export officiel.")
    report = import_records(args.db, items, args.limit, args.days)
    print(json.dumps(report, ensure_ascii=False, indent=2))
    if report["valid_candidates"] < args.limit:
        print(
            "ATTENTION : objectif non atteint ; aucun dossier n'a été inventé pour combler l'écart.",
            file=sys.stderr,
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
