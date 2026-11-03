#!/usr/bin/env python3
"""Regression tests for the reproducible policy catalogue import."""
import importlib.util
from pathlib import Path
import sqlite3
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "policy_catalog_import", ROOT / "tools" / "import_policy_catalog.py"
)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class PolicyCatalogImportTests(unittest.TestCase):
    def test_curated_corpus_and_repeated_import_preserve_vote_tables(self):
        curated = MODULE.curated_measures()
        self.assertGreaterEqual(len(curated), 35)
        self.assertEqual(3, len({row["originActor"] for row in curated}))
        with tempfile.TemporaryDirectory() as directory:
            dbpath = Path(directory) / "catalog.sqlite"
            first = MODULE.import_records(dbpath, curated, limit=200, days=90)
            self.assertEqual(len(curated), first["inserted"])
            second = MODULE.import_records(dbpath, curated, limit=200, days=90)
            self.assertEqual(0, second["inserted"])
            self.assertEqual(len(curated), second["database_total"])
            with sqlite3.connect(dbpath) as db:
                self.assertEqual(
                    0, db.execute("SELECT COUNT(*) FROM policy_votes").fetchone()[0]
                )
                self.assertEqual(
                    0, db.execute("SELECT COUNT(*) FROM policy_vote_history").fetchone()[0]
                )
                self.assertEqual(
                    0,
                    db.execute(
                        "SELECT COUNT(*) FROM policy_measures WHERE source_url LIKE '%?trk=%'"
                    ).fetchone()[0],
                )

    def test_existing_catalogue_without_status_is_upgraded_without_erasing_votes(self):
        curated = MODULE.curated_measures()
        with tempfile.TemporaryDirectory() as directory:
            dbpath = Path(directory) / "catalog.sqlite"
            with sqlite3.connect(dbpath) as db:
                old_schema = MODULE.SCHEMA.replace(
                    "    status TEXT NOT NULL DEFAULT 'open',\n", ""
                )
                db.executescript(old_schema)
            initial = MODULE.import_records(dbpath, curated, limit=2)
            self.assertEqual(2, initial["inserted"])
            with sqlite3.connect(dbpath) as db:
                measure_id = db.execute("SELECT id FROM policy_measures LIMIT 1").fetchone()[0]
                db.execute("INSERT INTO policy_votes(measure_id,citizen_id,choice,argument,updated_at) VALUES (?, ?, ?, ?, ?)",
                           (measure_id, "beta_" + "a" * 24, "pour", "", "2026-11-03T18:00:00+00:00"))
                db.commit()
            again = MODULE.import_records(dbpath, curated, limit=2)
            self.assertEqual(0, again["inserted"])
            with sqlite3.connect(dbpath) as db:
                cols = {r[1] for r in db.execute("PRAGMA table_info(policy_measures)")}
                self.assertIn("status", cols)
                self.assertEqual(
                    "open", db.execute("SELECT status FROM policy_measures LIMIT 1").fetchone()[0]
                )
                self.assertEqual(
                    1, db.execute("SELECT COUNT(*) FROM policy_votes").fetchone()[0]
                )

    def test_csv_delimiter_header_sorting_and_real_url_validation(self):
        with tempfile.TemporaryDirectory() as directory:
            csvpath = Path(directory) / "senat.csv"
            csvpath.write_text(
                "Session;Numéro de dépôt;Date de dépôt;Auteurs;Titre complet;"
                "Type de texte;URL du dossier législatif;Etat du dossier;Thème\n"
                "2026;450;02/11/2026;Groupe fictif;Proposition de loi visant à améliorer "
                "l'accès aux soins;Proposition de loi;http://www.senat.fr/dossier-legislatif/"
                "ppl26-450.html;En première lecture;Santé\n"
                "2026;451;03/11/2026;Groupe fictif;Proposition de loi visant à développer "
                "les transports collectifs;Proposition de loi;https://www.senat.fr/dossier-legislatif/"
                "ppl26-451.html;En première lecture;Transport\n"
                "2026;452;01/11/2026;Groupe fictif;Proposition de loi visant à fabriquer "
                "une loi fictive déjà promulguée;Proposition de loi;https://www.senat.fr/"
                "dossier-legislatif/ppl26-452.html;Promulguée;Divers\n"
                "2026;453;01/11/2026;Groupe fictif;Proposition de loi visant à créer "
                "une mesure mal sourcée;Proposition de loi;https://evil.example/"
                "not-legislation;En première lecture;Divers\n",
                encoding="utf-8",
            )
            actual = MODULE.senate_measures(csvpath)
            self.assertEqual(3, len(actual))
            self.assertIn("transports collectifs", actual[0]["label"])
            self.assertEqual("FR", actual[0]["territoryCode"])
            self.assertTrue(MODULE.validated(actual[0]))
            self.assertTrue(MODULE.validated(actual[1]))
            self.assertTrue(actual[1]["sourceUrl"].startswith("https://www.senat.fr/"))
            self.assertIsNone(MODULE.validated(actual[-1]))
            self.assertEqual("Santé", MODULE.theme("Améliorer l'accès aux soins"))

    def test_invalid_source_attribution_and_duplicate_do_not_create_votes(self):
        source = MODULE.curated_measures()[0].copy()
        self.assertIsNotNone(MODULE.validated(source))
        for bad_url in ["http://rassemblementnational.fr/22-mesures",
                        "https://evil.example/document", "file:///etc/passwd"]:
            candidate = source | {"sourceUrl": bad_url}
            self.assertIsNone(MODULE.validated(candidate))
        named = source | {"label": "Programme Marine Le Pen : " + source["label"]}
        self.assertIsNone(MODULE.validated(named))
        with tempfile.TemporaryDirectory() as directory:
            report = MODULE.import_records(
                Path(directory) / "db.sqlite", [source, source], limit=200
            )
            self.assertEqual(1, report["inserted"])
            self.assertEqual(1, report["database_total"])
            self.assertEqual(1, report["rejected_or_duplicate"])


if __name__ == "__main__":
    unittest.main()
