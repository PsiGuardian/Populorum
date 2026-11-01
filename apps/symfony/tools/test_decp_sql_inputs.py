#!/usr/bin/env python3
"""SQL injection regression on temporary DECP imports; no national data touched."""
from __future__ import annotations

import contextlib
import copy
import io
import json
from pathlib import Path
import sqlite3
import tempfile

from import_decp import SCHEMA, contract_key, ensure_fts, ensure_history_schema, import_file

PAYLOADS = (
    "' OR 1=1 --",
    "'; DROP TABLE contracts; --",
    "' UNION SELECT name FROM sqlite_master --",
    '" OR "x"="x',
    "x' /* comment */",
    '") OR (1=1',
    '%_!',
    "x\nUNION SELECT 1",
    "x\x00y",
)
FIELDS = (
    ("id",), ("acheteur", "id"), ("codeCPV",), ("objet",),
    ("nature",), ("procedure",), ("montant",),
    ("dateNotification",), ("datePublicationDonnees",),
    ("lieuExecution", "typeCode"), ("lieuExecution", "code"),
    ("titulaires", 0, "titulaire", "id"),
    ("titulaires", 0, "titulaire", "typeIdentifiant"),
    ("modifications", 0, "modification", "id"),
    ("modifications", 0, "modification", "montant"),
    ("modifications", 0, "modification", "dateNotificationModification"),
    ("modifications", 0, "modification", "dureeMois"),
    ("actesSousTraitance", 0, "acteSousTraitance", "id"),
    ("actesSousTraitance", 0, "acteSousTraitance", "montant"),
    ("actesSousTraitance", 0, "acteSousTraitance", "sousTraitant", "id"),
    ("actesSousTraitance", 0, "acteSousTraitance", "sousTraitant", "typeIdentifiant"),
)
CONCESSION_FIELDS = (
    ("id",), ("autoriteConcedante", "id"),
    ("valeurGlobale",), ("montantSubventionPublique",),
    ("objet",), ("dateSignature",),
    ("concessionnaires", 0, "concessionnaire", "id"),
    ("donneesExecution", 0, "donneesAnnuelles", "depensesInvestissement"),
)


def market(identifier: str) -> dict:
    return {
        "nature": "Marché", "id": identifier, "acheteur": {"id": "21000000000011"},
        "codeCPV": "45233140", "objet": "Rénovation fictive de route",
        "procedure": "Procédure ouverte", "montant": 1234,
        "dateNotification": "2025-01-10", "datePublicationDonnees": "2025-01-15",
        "lieuExecution": {"typeCode": "Code commune", "code": "66134"},
        "titulaires": [{"titulaire": {"id": "12345678900011", "typeIdentifiant": "SIRET"}}],
        "modifications": [{"modification": {
            "id": "M1", "montant": 90, "dateNotificationModification": "2025-03-01",
            "dureeMois": 1,
        }}],
        "actesSousTraitance": [{"acteSousTraitance": {
            "id": "S1", "montant": 15, "dateNotification": "2025-04-01",
            "sousTraitant": {"id": "98765432100011", "typeIdentifiant": "SIRET"},
        }}],
    }


def concession(identifier: str) -> dict:
    return {
        "nature": "Contrat de concession", "id": identifier,
        "autoriteConcedante": {"id": "21000000000011"},
        "objet": "Gestion fictive des transports", "valeurGlobale": 4567,
        "dateSignature": "2025-01-10", "datePublicationDonnees": "2025-01-15",
        "concessionnaires": [{"concessionnaire": {"id": "98765432100011", "typeIdentifiant": "SIRET"}}],
        "donneesExecution": [{"donneesAnnuelles": {"depensesInvestissement": 50}}],
        "montantSubventionPublique": 30,
    }


def set_field(obj: dict, path: tuple, value: str) -> None:
    current = obj
    for key in path[:-1]:
        current = current[key]
    current[path[-1]] = value


def table_names(db: sqlite3.Connection) -> set[str]:
    return {row[0] for row in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="decp-sql-input-") as folder:
        root = Path(folder)
        db = sqlite3.connect(root / "fixture.sqlite")
        db.executescript(SCHEMA)
        ensure_history_schema(db)
        ensure_fts(db)
        initial_tables = table_names(db)

        # Sentinel contract proves subsequent imports do not rewrite an unrelated row.
        sentinel = market("SENTINEL-2025")
        path = root / "decp-sentinel.json"
        path.write_text(json.dumps({"marches": {"marche": [sentinel], "contrat-concession": []}}), encoding="utf-8")
        with contextlib.redirect_stdout(io.StringIO()):
            assert import_file(db, path)
        key = contract_key(sentinel, "market")
        before = db.execute(
            "SELECT original_id,object,amount_initial_cents FROM contracts WHERE contract_key=?", (key,)
        ).fetchone()
        assert before is not None

        tested = 0
        for kind, fields, builder in (
            ("marche", FIELDS, market),
            ("contrat-concession", CONCESSION_FIELDS, concession),
        ):
            for field in fields:
                for payload in PAYLOADS:
                    record = copy.deepcopy(builder(f"TEST-{tested:05d}"))
                    set_field(record, field, payload)
                    document = {"marches": {"marche": [], "contrat-concession": []}}
                    document["marches"][kind] = [record]
                    source = root / f"decp-fuzz-{tested:05d}.json"
                    source.write_text(json.dumps(document, ensure_ascii=False), encoding="utf-8")
                    with contextlib.redirect_stdout(io.StringIO()):
                        assert import_file(db, source, source_url=payload)
                    tested += 1
                    assert table_names(db) == initial_tables, (kind, field, payload)
                    assert db.execute(
                        "SELECT original_id,object,amount_initial_cents FROM contracts WHERE contract_key=?", (key,)
                    ).fetchone() == before, (kind, field, payload)
                    assert db.execute("PRAGMA quick_check").fetchone()[0] == "ok"

        assert db.execute("SELECT COUNT(*) FROM contracts").fetchone()[0] == tested + 1
        assert db.execute("SELECT COUNT(*) FROM imports").fetchone()[0] == tested + 1
        db.close()
        print(f"OK: {tested} injections de test DECP, toutes les tables intactes et contrat témoin inchangé")


if __name__ == "__main__":
    main()
