#!/usr/bin/env python3
"""Tests hermétiques de l'import des seules propositions de loi du Sénat."""
import importlib.util
from pathlib import Path
import sqlite3
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "policy_catalog_import", ROOT / "tools" / "import_policy_catalog.py"
)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)

CSV = (
    "Session;Numéro de dépôt;Date de dépôt;Auteurs;Titre complet;"
    "Type de texte;URL du dossier législatif;Etat du dossier;Thème\n"
    "2026;450;02/11/2026;Sénateur témoin;Proposition de loi visant à améliorer "
    "l'accès aux soins;Proposition de loi;http://www.senat.fr/dossier-legislatif/"
    "ppl26-450.html;En première lecture;Santé\n"
    "2026;451;03/11/2026;Autre sénateur témoin;Proposition de loi visant à développer "
    "les transports collectifs;Proposition de loi;https://www.senat.fr/dossier-legislatif/"
    "ppl26-451.html;En première lecture;Transports\n"
    "2026;452;01/11/2026;Auteur témoin;Proposition de loi visant à ajouter "
    "une prestation;Proposition de loi;https://www.senat.fr/dossier-legislatif/"
    "ppl26-452.html;Promulguée;Divers\n"
    "2026;453;01/11/2026;Auteur témoin;Proposition de loi visant à abaisser "
    "un tarif;Proposition de loi;https://www.senat.fr/dossier-legislatif/"
    "ppl26-453.html;Caduc;Divers\n"
    "2026;454;01/11/2026;Auteur témoin;Proposition de résolution sur "
    "une question;Proposition de résolution;https://www.senat.fr/dossier-legislatif/"
    "ppl26-454.html;En première lecture;Divers\n"
    "2026;455;01/11/2026;Auteur témoin;Proposition de loi visant à produire "
    "un faux dossier;Proposition de loi;https://evil.example/"
    "not-legislation;En première lecture;Divers\n"
)


class OfficialBillImportTests(unittest.TestCase):
    def sample(self, root: Path):
        csvpath = root / "senat.csv"
        csvpath.write_text(CSV, encoding="utf-8")
        return csvpath

    def test_official_csv_filters_non_bills_and_validates_issuer(self):
        with tempfile.TemporaryDirectory() as directory:
            records = MODULE.senate_measures(self.sample(Path(directory)))
        self.assertEqual(3, len(records))
        valid = [MODULE.validated(x) for x in records]
        self.assertEqual(2, sum(x is not None for x in valid))
        self.assertTrue(records[0]["label"].endswith("transports collectifs"))
        self.assertEqual("https://www.senat.fr", records[1]["sourceUrl"][:20])
        self.assertEqual("FR", records[0]["territoryCode"])
        self.assertEqual("proposition_de_loi", records[0]["sourceType"])
        self.assertEqual("Santé", MODULE.theme("Améliorer l'accès aux soins"))

    def test_import_reimport_and_legacy_programme_is_hidden_without_loss(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            rows = MODULE.senate_measures(self.sample(root))
            dbpath = root / "catalog.sqlite"
            first = MODULE.import_records(dbpath, rows, limit=200)
            self.assertEqual(2, first["inserted"])
            self.assertEqual(2, first["database_total"])
            with sqlite3.connect(dbpath) as db:
                active_id = db.execute(
                    "SELECT id FROM policy_measures WHERE source_type = 'proposition_de_loi' LIMIT 1"
                ).fetchone()[0]
                db.execute(
                    "INSERT INTO policy_votes (measure_id, citizen_id, choice, argument, updated_at)"
                    " VALUES (?, ?, ?, ?, ?)",
                    (active_id, "beta_" + "a" * 24, "pour", "", "2026-11-03T18:00:00+00:00"),
                )
                db.execute(
                    "INSERT INTO policy_measures (id, canonical_key, label, category,"
                    " territory_code, source_type, source_url, source_date,"
                    " source_reference, origin_actor, legal_status, review_status,"
                    " opened_at, closes_at, created_at)"
                    " VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    ("pm_" + "b" * 24, "b" * 64, "Ancien programme fictif",
                     "Autres", "FR", "programme", "https://example.invalid",
                     "2022", "M1", "Ancienne source", "programme", "historique",
                     "2026-11-03T18:00:00+00:00", "2027-01-01T00:00:00+00:00",
                     "2026-11-03T18:00:00+00:00"),
                )
                db.commit()
            again = MODULE.import_records(dbpath, rows, limit=200)
            self.assertEqual(0, again["inserted"])
            self.assertEqual(2, again["database_total"])
            with sqlite3.connect(dbpath) as db:
                self.assertEqual(3, db.execute(
                    "SELECT COUNT(*) FROM policy_measures"
                ).fetchone()[0])
                self.assertEqual(1, db.execute(
                    "SELECT COUNT(*) FROM policy_votes"
                ).fetchone()[0])

    def test_non_official_and_inactive_entries_never_validate(self):
        example = {
            "label": "Proposition de loi portant une mesure nationale de test",
            "category": "Divers", "territoryCode": "FR",
            "sourceType": "proposition_de_loi",
            "sourceUrl": "https://www.senat.fr/dossier-legislatif/ppl26-1.html",
            "sourceDate": "03/11/2026", "sourceReference": "1",
            "originActor": "Auteur témoin", "legalStatus": "En première lecture",
            "reviewStatus": "texte officiel",
        }
        self.assertIsNotNone(MODULE.validated(example))
        for wrong in (
            {"sourceType": "programme"},
            {"sourceUrl": "https://evil.example/foo"},
            {"sourceUrl": "http://www.senat.fr/dossier-legislatif/ppl26-1.html"},
            {"territoryCode": "75056"},
            {"legalStatus": "Caduc"},
            {"legalStatus": "Non adopté"},
            {"legalStatus": "Promulguée"},
        ):
            self.assertIsNone(MODULE.validated(example | wrong))

    def test_homonymous_proposals_are_separate_if_official_dossiers_differ(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            records = MODULE.senate_measures(self.sample(root))
            same_title = records[1].copy()
            same_title["sourceUrl"] = (
                "https://www.senat.fr/dossier-legislatif/ppl26-999.html"
            )
            imported = MODULE.import_records(
                root / "catalog.sqlite", [records[1], same_title], limit=200
            )
            self.assertEqual(2, imported["inserted"])
            self.assertEqual(2, imported["database_total"])

    def test_import_requires_official_file_and_does_not_invent_records(self):
        cmd = [sys.executable, str(ROOT / "tools/import_policy_catalog.py")]
        result = subprocess.run(cmd, capture_output=True, text=True)
        self.assertNotEqual(0, result.returncode)
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            csvpath = root / "empty.csv"
            csvpath.write_text(CSV.splitlines()[0] + "\n", encoding="utf-8")
            result = subprocess.run(cmd + ["--db", str(root / "db.sqlite"),
                                           "--senat-csv", str(csvpath)],
                                    capture_output=True, text=True)
            self.assertNotEqual(0, result.returncode)


if __name__ == "__main__":
    unittest.main()
