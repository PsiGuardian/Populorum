#!/usr/bin/env python3
"""Tests hors réseau des ressources parlementaires réellement découvertes."""
import importlib.util
from pathlib import Path
import sqlite3
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "senate_enrichment", ROOT / "tools" / "enrich_senate_dossiers.py"
)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)

DOSSIER = """
<!doctype html><html><body>
<h1>Défense extérieure contre l'incendie</h1>
<h2>Dépôt au Sénat</h2>
<a href="/leg/ppl22-262.html">Texte</a> de deux sénateurs
<a href="/leg/exposes-des-motifs/ppl22-262-expose.html">exposé des motifs</a>
<h2>Première lecture au Sénat</h2>
<a href="/amendements/2022-2023/377/accueil.html">Amendements</a>
<a href="/rap/l22-376/l22-376.html">Rapport</a>
<a href="/leg/ppl22-377.html">Texte de la commission</a>
<a href="/seances/s202303/s20230315/s20230315003.html">Compte rendu intégral des débats</a>
<a href="/dossier-legislatif/ppl22-262.html">Dossier législatif</a>
<a href="https://evil.example/document">Lien non officiel</a>
<a href="javascript:alert(1)">Lien dangereux</a>
<h2>Assemblée nationale</h2>
<a href="https://www.assemblee-nationale.fr/dyn/16/dossiers/suite_deci">
Dossier législatif sur le site de l'Assemblée nationale</a>
</body></html>
"""

EXPOSE = """
<!doctype html><html><body>
<h1>Défense extérieure contre l'incendie</h1>
<p>EXPOSÉ DES MOTIFS</p>
<p>Mesdames, Messieurs,</p>
<p>La défense extérieure contre l'incendie vise à garantir l'alimentation en eau
des moyens des services d'incendie et de secours par l'intermédiaire de points d'eau
identifiés à cette fin.</p>
<p>Cette politique publique est présentée dans l'exposé comme une organisation
dont la charge peut nécessiter une analyse territoriale plus approfondie et
une clarification des contraintes financières et techniques pour les communes.</p>
<p>Autre constat présenté par les auteurs dont le statut de preuve doit
rester explicite et ne doit jamais devenir automatiquement un fait certifié.</p>
</body></html>
"""


class SenateDossierEnrichmentTests(unittest.TestCase):
    def test_verified_document_categories_and_motives_are_from_source(self):
        url = "https://www.senat.fr/dossier-legislatif/ppl22-262.html"
        docs, excerpts, source = MODULE.extract(
            url, DOSSIER, fetch_motives=lambda value: EXPOSE
        )
        kinds = {row["kind"] for row in docs}
        self.assertTrue({"texte", "expose", "amendement", "rapport",
                         "commission", "debat", "assemblee"}.issubset(kinds))
        self.assertNotIn("evil.example", " ".join(row["url"] for row in docs))
        self.assertTrue(all(MODULE.official(row["url"]) for row in docs))
        self.assertEqual(3, len(excerpts))
        self.assertIn("La défense extérieure", excerpts[0])
        self.assertEqual(
            "https://www.senat.fr/leg/exposes-des-motifs/ppl22-262-expose.html",
            source,
        )
        self.assertTrue(all("Mesdames" not in p for p in excerpts))
        self.assertNotIn(url, [row["url"] for row in docs])

    def test_generic_report_links_are_not_indexed_as_parliamentary_reports(self):
        dossier = """
        <html><body>
        <nav>
          <a href="/rapports/derniers-rapports.html">Derniers rapports</a>
          <a href="/rap/">Tous les rapports et documents de travail</a>
          <a href="/basile/rechercheRapport.do?rch=ra&amp;dp=1+an">
            Rapports d'informations</a>
        </nav>
        <h2>Travaux de commission</h2>
        <a href="/rap/l22-376/l22-376.html">Rapport</a>
        <a href="/rap/r20-760/r20-760_mono.html">Rapport d'information n° 760</a>
        </body></html>
        """
        docs, _, _ = MODULE.extract(
            "https://www.senat.fr/dossier-legislatif/ppl22-262.html",
            dossier,
        )
        reports = [doc for doc in docs if doc["kind"] == "rapport"]
        self.assertEqual(2, len(reports))
        self.assertEqual([
            "https://www.senat.fr/rap/l22-376/l22-376.html",
            "https://www.senat.fr/rap/r20-760/r20-760_mono.html",
        ], [item["url"] for item in reports])
        for url in (
            "https://www.senat.fr/rap/",
            "https://www.senat.fr/rapports/derniers-rapports.html",
            "https://www.senat.fr/basile/rechercheRapport.do?rch=ra",
        ):
            self.assertFalse(MODULE.direct_report(url))
            self.assertIsNone(MODULE.classify("Rapport", url))

    def test_fresh_dossier_cache_contains_only_real_reports(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "catalog.sqlite"
            db = sqlite3.connect(path)
            db.execute(
                "CREATE TABLE policy_measures "
                "(id TEXT PRIMARY KEY, source_type TEXT, source_url TEXT)"
            )
            bill_id = "pm_" + "f" * 24
            url = "https://www.senat.fr/dossier-legislatif/ppl22-262.html"
            db.execute(
                "INSERT INTO policy_measures VALUES (?, ?, ?)",
                (bill_id, "proposition_de_loi", url),
            )
            db.executescript(MODULE.SCHEMA)
            html = """
            <html><body>
              <nav>
                <a href="/rapports/derniers-rapports.html">Derniers rapports</a>
                <a href="/rap/">Tous les rapports et documents de travail</a>
                <a href="/basile/rechercheRapport.do?rch=ra">Rapports d'information</a>
              </nav>
              <h2>Documents associés à cette proposition</h2>
              <a href="/rap/l22-376/l22-376.html">Rapport</a>
              <a href="/rap/r20-760/r20-760_mono.html">Rapport d'information 760</a>
            </body></html>
            """
            docs, excerpts, source = MODULE.extract(url, html)
            MODULE.store(db, bill_id, docs, excerpts, source)
            stored = db.execute(
                "SELECT url FROM policy_senate_documents "
                "WHERE measure_id = ? AND kind = 'rapport' ORDER BY sort_order",
                (bill_id,),
            ).fetchall()
            self.assertEqual([
                ("https://www.senat.fr/rap/l22-376/l22-376.html",),
                ("https://www.senat.fr/rap/r20-760/r20-760_mono.html",),
            ], stored)
            self.assertEqual(
                2, db.execute("SELECT COUNT(*) FROM policy_senate_documents").fetchone()[0]
            )
            db.close()

    def test_never_assume_missing_report_or_expose_exists(self):
        docs, excerpts, source = MODULE.extract(
            "https://www.senat.fr/dossier-legislatif/ppl26-1.html",
            "<html><h1>Dossier sans documents</h1></html>",
            fetch_motives=lambda _: self.fail("Should not fetch motives"),
        )
        self.assertEqual([], docs)
        self.assertEqual([], excerpts)
        self.assertEqual("", source)

    def test_official_url_allowlist(self):
        self.assertTrue(MODULE.official("https://www.senat.fr/leg/ppl22-262.html"))
        self.assertTrue(MODULE.official("https://data.senat.fr/dosleg/"))
        self.assertTrue(MODULE.official("https://www.assemblee-nationale.fr/"))
        for url in ["javascript:alert(1)", "http://www.senat.fr/",
                    "https://senat.fr.evil.example/", "https://evil.example/",
                    "https://user:password@www.senat.fr/"]:
            self.assertFalse(MODULE.official(url))
        self.assertFalse(MODULE.dossier_url("https://senat.fr.evil.example/ppl.html"))
        self.assertTrue(MODULE.dossier_url(
            "https://www.senat.fr/dossier-legislatif/ppl22-262.html"
        ))

    def test_atomic_refresh_preserves_existing_votes_and_branches(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "catalog.sqlite"
            db = sqlite3.connect(path)
            db.execute(
                "CREATE TABLE policy_measures "
                "(id TEXT PRIMARY KEY, source_type TEXT, source_url TEXT)"
            )
            db.execute(
                "INSERT INTO policy_measures VALUES (?, ?, ?)",
                ("pm_" + "f" * 24, "proposition_de_loi",
                 "https://www.senat.fr/dossier-legislatif/ppl22-262.html"),
            )
            db.execute(
                "CREATE TABLE policy_votes "
                "(measure_id TEXT, citizen_id TEXT, choice TEXT)"
            )
            db.execute(
                "INSERT INTO policy_votes VALUES (?, ?, ?)",
                ("pm_" + "f" * 24, "beta_" + "a" * 24, "pour"),
            )
            db.execute("CREATE TABLE policy_deliberations (id TEXT PRIMARY KEY)")
            db.execute("INSERT INTO policy_deliberations VALUES ('pc_example')")
            db.executescript(MODULE.SCHEMA)
            docs, excerpts, source = MODULE.extract(
                "https://www.senat.fr/dossier-legislatif/ppl22-262.html",
                DOSSIER, fetch_motives=lambda _: EXPOSE,
            )
            id_value = "pm_" + "f" * 24
            MODULE.store(db, id_value, docs, excerpts, source)
            MODULE.store(db, id_value, docs, excerpts, source)
            self.assertEqual(len(docs), db.execute(
                "SELECT COUNT(*) FROM policy_senate_documents"
            ).fetchone()[0])
            self.assertEqual(1, db.execute(
                "SELECT COUNT(*) FROM policy_senate_context"
            ).fetchone()[0])
            self.assertEqual(1, db.execute(
                "SELECT COUNT(*) FROM policy_votes"
            ).fetchone()[0])
            self.assertEqual(1, db.execute(
                "SELECT COUNT(*) FROM policy_deliberations"
            ).fetchone()[0])
            db.close()


if __name__ == "__main__":
    unittest.main()
