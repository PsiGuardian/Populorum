#!/usr/bin/env python3
"""Smoke test a real PHP built-in HTTP server against a fresh installed demo.

Uses real HTTP and session cookies (never the artificial PHP test client).
No external dependencies. Refuses 5xx, 4xx and empty or PHP diagnostic pages.
"""
from __future__ import annotations

from contextlib import closing
import http.cookiejar
import os
import sqlite3
from pathlib import Path
import socket
import statistics
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
ROUTES = (
    ("/", ("Démos Kratos", "Propositions de loi")),
    ("/proposals", ("Propositions de loi",)),
    ("/budget", ("Budget",)),
    ("/mesures", ("Propositions de loi",)),
)

def main() -> None:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        port = s.getsockname()[1]
    url = f"http://127.0.0.1:{port}"
    cookies = http.cookiejar.CookieJar()
    browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))
    with tempfile.TemporaryDirectory(prefix="populorum-http-smoke-") as temp, \
            tempfile.TemporaryFile(mode="w+t", encoding="utf-8") as logs:
        source_db = ROOT / "var" / "populorum.sqlite"
        if not source_db.is_file():
            raise RuntimeError("Base de démonstration absente : lancer l'installateur en premier")
        temporary_db = Path(temp) / "http-smoke.sqlite"
        # Backup API produces a consistent database. The live user DB is never modified.
        with sqlite3.connect(source_db.as_uri() + "?mode=ro", uri=True) as source:
            with sqlite3.connect(temporary_db) as destination:
                source.backup(destination, pages=1000, sleep=0.05)
        # Fixture de test technique, jamais exposée sur une installation utilisateur.
        official_csv = Path(temp) / "senat-official-fixture.csv"
        official_csv.write_text(
            "Session;Numéro de dépôt;Date de dépôt;Auteurs;Titre complet;"
            "Type de texte;URL du dossier législatif;Etat du dossier;Thème\n"
            "2026;451;03/11/2026;Auteur de test;Proposition de loi concernant "
            "le contrôle des frais municipaux;Proposition de loi;"
            "https://www.senat.fr/dossier-legislatif/ppl26-451.html;"
            "En première lecture;Finances\n",
            encoding="utf-8",
        )
        temporary_catalog = Path(temp) / "policy-catalog.sqlite"
        subprocess.run(
            ["python3", "tools/import_policy_catalog.py", "--db",
             str(temporary_catalog), "--senat-csv", str(official_csv),
             "--limit", "1"],
            cwd=ROOT, check=True, capture_output=True, text=True,
        )
        # Source documentary fixture: the UI uses an offline institutional cache,
        # not a web request during an ordinary page visit.
        with sqlite3.connect(temporary_catalog) as senate_db:
            bill_id = senate_db.execute(
                "SELECT id FROM policy_measures WHERE source_type = 'proposition_de_loi'"
            ).fetchone()[0]
            senate_db.executescript(
                "CREATE TABLE policy_senate_documents ("
                "measure_id TEXT, kind TEXT, label TEXT, url TEXT, "
                "source_section TEXT, sort_order INTEGER);"
                "CREATE TABLE policy_senate_context ("
                "measure_id TEXT PRIMARY KEY, excerpt_text TEXT, "
                "excerpt_url TEXT, synced_at TEXT);"
            )
            senate_db.execute(
                "INSERT INTO policy_senate_documents VALUES (?, ?, ?, ?, ?, ?)",
                (bill_id, "rapport", "Rapport parlementaire de référence",
                 "https://www.senat.fr/rap/l22-376/l22-376.html",
                 "Travaux de commission", 0),
            )
            senate_db.execute(
                "INSERT INTO policy_senate_context VALUES (?, ?, ?, ?)",
                (bill_id, "Les auteurs avancent des arguments qui doivent être "
                 "vérifiés dans le document officiel et les analyses indépendantes.",
                 "https://www.senat.fr/leg/exposes-des-motifs/ppl22-262-expose.html",
                 "2026-11-03T20:00:00+00:00"),
            )
        environment = dict(os.environ)
        environment.update(APP_ENV="demo", APP_DEBUG="0", POPULORUM_DEMO="1",
                           POPULORUM_TIMING="1",
                           POPULORUM_POLICY_CATALOG_PATH=str(temporary_catalog),
                           DATABASE_URL="sqlite:///" + str(temporary_db))
        # Do not set POPULORUM_SCENARIO=reference: test the actual install.
        environment.pop("POPULORUM_SCENARIO", None)
        server = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{port}", "-t", "public"],
            cwd=ROOT, env=environment, stdin=subprocess.DEVNULL, stdout=logs,
            stderr=subprocess.STDOUT,
        )
        try:
            for path, fragments in ROUTES:
                response = None
                start = time.monotonic()
                while time.monotonic() - start < 45:
                    if server.poll() is not None:
                        raise RuntimeError(f"PHP exited unexpectedly with {server.returncode}")
                    try:
                        with closing(browser.open(url + path, timeout=28)) as result:
                            status = result.status
                            body = result.read(5_000_000).decode("utf-8", "replace")
                        if status != 200:
                            raise AssertionError(f"{path}: HTTP {status}")
                        if len(body) < 250 or not any(word.casefold() in body.casefold() for word in fragments):
                            raise AssertionError(f"{path}: HTML empty or expected content missing")
                        if "Fatal error" in body or "Uncaught Exception" in body:
                            raise AssertionError(f"{path}: PHP fatal leaked into HTML")
                        print(f"PASS {path}: HTTP {status}, {len(body)} characters", flush=True)
                        response = status
                        break
                    except urllib.error.HTTPError as exc:
                        sample = exc.read(5000).decode("utf-8", "replace")
                        raise AssertionError(f"{path}: HTTP {exc.code} : {sample[:350]}") from exc
                    except (TimeoutError, urllib.error.URLError):
                        time.sleep(0.3)
                if response != 200:
                    raise AssertionError(f"{path}: PHP did not return HTTP 200 in time")

            # Service identifiers are path-only. No redirects or query fallback.
            for path, expected_status in (
                ("/services?plan=water", 400),
                ("/services/water?scenario=leak", 400),
                ("/services/invalid", 404),
                ("/services/energy/leak", 404),
                ("/services/water/invalid", 404),
            ):
                try:
                    with closing(browser.open(url + path, timeout=28)) as response:
                        raise AssertionError(
                            f"{path}: expected HTTP {expected_status}, got {response.status}"
                        )
                except urllib.error.HTTPError as error:
                    assert error.code == expected_status, (path, error.code)
                    print(f"PASS {path}: HTTP {error.code}", flush=True)

            # Canonical public filters use path segments, never query strings.
            from urllib.parse import urlencode
            for old, canonical in (
                ('/marches-publics?q=web&kind=&year=&buyer=&supplier=&city=&department=',
                 '/marches-publics/q/web'),
                ('/communes?q=66300', '/communes/q/66300'),
                ('/convergence?communes_q=66300', '/convergence/communes_q/66300'),
                ('/budget-et-priorites?scope=FR', '/budget-et-priorites/scope/FR'),
            ):
                with closing(browser.open(url + old, timeout=28)) as result:
                    assert result.status == 200, (old, result.status)
                    assert result.geturl().split('#', 1)[0] == url + canonical, result.geturl()
                with closing(browser.open(url + canonical, timeout=28)) as result:
                    assert result.status == 200
                    assert '?' not in result.geturl(), result.geturl()
                print('PASS canonical path ' + canonical, flush=True)
            payload = urlencode({'q': 'web', 'kind': 'market', 'year': '2025', 'buyer': ''}).encode()
            with closing(browser.open(urllib.request.Request(
                url + '/filtres/marches-publics', data=payload, method='POST'
            ), timeout=28)) as result:
                assert result.status == 200
                assert result.geturl().split('#', 1)[0] == url + '/marches-publics/q/web/kind/market/year/2025', result.geturl()
            print('PASS public market search POST-to-canonical-GET', flush=True)

            # Verify actual HTTP search and suggestion results against the installed INSEE index.
            postal_cases = (
                ('/communes?q=76600', ('Le Havre',), ('Saint-Léonard',)),
                ('/communes?q=66300', ('Passa', 'Thuir', 'Trouillas'), ()),
                ('/communes?q=INSEE%3A76600', ('Saint-Léonard',), ()),
            )
            for path, required, excluded in postal_cases:
                with closing(browser.open(url + path, timeout=28)) as result:
                    assert result.status == 200, (path, result.status)
                    html = result.read(5_000_000).decode('utf-8', 'replace')
                for expected in required:
                    assert expected in html, (path, 'missing', expected)
                for unexpected in excluded:
                    assert unexpected not in html, (path, 'unexpected', unexpected)
                print(f'PASS {path}: HTTP 200, correct postal/INSEE mapping', flush=True)
            with closing(browser.open(url + '/communes/suggestions?q=66300', timeout=28)) as result:
                assert result.status == 200
                import json
                data = json.load(result)
            assert data['postalQuery'] is True
            assert len(data['communes']) == 18, len(data['communes'])
            assert len({city['code'] for city in data['communes']}) == 18
            assert all(row['postalCodes'] == '66300' for row in data['communes']), data['communes']
            print('PASS /communes/suggestions?q=66300: all 18 municipalities', flush=True)
            with closing(browser.open(url + '/communes/suggestions?q=76600', timeout=28)) as result:
                assert result.status == 200
                havre = json.load(result)
            assert [item['name'] for item in havre['communes']] == ['Le Havre']
            assert havre['communes'][0]['postalCodes'] == '76600'
            print('PASS 76600: no extra 76610/76620 displayed as matches', flush=True)
            for prefix in ('66', '663'):
                with closing(browser.open(url + '/communes/suggestions?q=' + prefix, timeout=28)) as result:
                    assert result.status == 200
                    data = json.load(result)
                assert data['postalQuery'] is True
                assert data['communes']
                for row in data['communes']:
                    assert all(code.strip().startswith(prefix) for code in row['postalCodes'].split(',')), (prefix, row)
                print('PASS postal prefix ' + prefix + ': only matching postal codes', flush=True)


            # The national convergence atlas must show all municipalities without rendering them all.
            atlas_cases = (
                ('/convergence', ('34 875', 'Communes INSEE', 'Page suivante')),
                ('/convergence?communes_q=66300', ('Passa', 'Thuir', '18')),
                ('/convergence?communes_q=76600', ('Le Havre', 'CP 76600')),
                ('/convergence?commune=66134', ('Passa', 'FICHE COMMUNALE')),
            )
            for path, labels in atlas_cases:
                with closing(browser.open(url + path, timeout=28)) as result:
                    assert result.status == 200, (path, result.status)
                    html = result.read(5_000_000).decode('utf-8', 'replace')
                for label in labels:
                    assert label in html, (path, 'missing', label)
                assert html.count('class="atlas-city"') <= 36, (path, 'unbounded atlas render')
                print('PASS national convergence atlas ' + path, flush=True)
            # Permanent CI page timing inventory; no temporary benchmarking script.
            # Warm one request, then measure HTTP and server request time separately.
            timing_routes = (
                '/', '/territoires', '/chemins', '/consultations', '/experiences',
                '/convergence', '/convergence/communes_q/66300',
                '/memoire', '/transparence', '/infrastructure', '/gouvernance',
                '/participer', '/charte', '/recits', '/services/water', '/services/water/contamination',
                '/budget-et-priorites', '/besoins-et-devis', '/communes',
                '/communes/q/66300', '/marches-publics', '/marches-publics/q/web',
            )
            timings = []
            import re
            for path in timing_routes:
                http_ms = []
                php_ms = []
                for i in range(3):  # initial warm-up plus two measured requests
                    started = time.perf_counter()
                    with closing(browser.open(url + path, timeout=28)) as response:
                        assert response.status == 200, (path, response.status)
                        response.read()
                        timing = response.headers.get('Server-Timing', '')
                    elapsed = (time.perf_counter() - started) * 1000
                    if i:
                        http_ms.append(elapsed)
                        match = re.search(r'(?:^|,\s*)request;dur=([0-9.]+)', timing)
                        if match:
                            php_ms.append(float(match.group(1)))
                timings.append((path, statistics.median(http_ms),
                                statistics.median(php_ms) if php_ms else None))
            print('===== WARM HTTP PAGE TIMINGS / fresh CI SQLite fixture (ms) =====', flush=True)
            for path, http_ms_median, php_ms_median in sorted(timings, key=lambda x: x[1], reverse=True):
                print(f'PAGE {path:38} HTTP={http_ms_median:8.2f}  PHP={php_ms_median if php_ms_median is not None else "n/a"}', flush=True)
            print('NOTE: CI fixture timings are not timings of the optional 1.7GB DECP production DB.', flush=True)

            # Stateless beta profile: create, revisit, delete without browser cookies.
            profile_browser = urllib.request.build_opener()
            with closing(profile_browser.open(url + '/compte-test', timeout=28)) as response:
                assert response.status == 200
                profile_form = response.read().decode('utf-8')
                assert 'Créer un compte de test' in profile_form
                assert 'POPULORUM_BETA' not in str(response.headers), 'Unexpected profile cookie'
            form_token = re.search(r'name="_token" value="([a-f0-9]{64})"', profile_form)
            assert form_token, 'Stateless profile form token missing'
            details = urlencode({'_token': form_token.group(1), 'first_name': 'Alex',
                                 'last_name': 'Fictif', 'age': '32', 'commune_code': '66134'}).encode()
            with closing(profile_browser.open(urllib.request.Request(
                url + '/compte-test', data=details, method='POST',
                headers={'Origin': url}
            ), timeout=28)) as response:
                assert response.status == 200
                html = response.read().decode('utf-8', 'replace')
                assert 'Alex Fictif' in html, 'IP-only profile not restored'
                assert 'POPULORUM_BETA' not in str(response.headers), 'Unexpected profile cookie'
            with closing(profile_browser.open(url + '/compte-test', timeout=28)) as response:
                assert 'Alex Fictif' in response.read().decode('utf-8'), 'Revisit failed without cookie'
            print('PASS IP-only beta profile POST/revisit, no account cookie', flush=True)
            for page_path, heading in (
                ('/chemins', 'Explorer les propositions de loi'),
                ('/consultations', 'Voter sur les propositions de loi'),
            ):
                with closing(profile_browser.open(url + page_path, timeout=28)) as response:
                    page_html = response.read().decode('utf-8', 'replace')
                    assert response.status == 200
                    assert heading in page_html, 'Expected distinct page: ' + page_path
                    assert 'Alex Fictif' in page_html
                    assert 'policy-pages.css' in page_html
                    assert 'class="proposal-card"' not in page_html
            # Source politique visible immédiatement ; seul le décompte du vote est différé.
            with closing(profile_browser.open(url + '/mesures?scope=FR', timeout=28)) as response:
                assert response.status == 200
                index_html = response.read().decode('utf-8', 'replace')
            match = re.search(r'href="(/mesures/pm_[a-f0-9]{24})"', index_html)
            assert match, 'No national policy measure available in catalogue: ' + index_html[:1300]
            detail_path = match.group(1)
            with closing(profile_browser.open(url + detail_path, timeout=28)) as response:
                detail_html = response.read().decode('utf-8', 'replace')
                assert response.status == 200
                assert 'Enregistrer ma position fictive' in detail_html
                assert 'Auteur de test' in detail_html
                assert 'État parlementaire au dernier import' in detail_html
                assert 'Les auteurs avancent des arguments' in detail_html
                assert 'Rapport parlementaire de référence' in detail_html
                assert 'Ouvrir le dossier officiel du Sénat' in detail_html
                assert 'Consultation clôturée : résultat' not in detail_html
                assert 'La délibération s’ouvre après votre vote' in detail_html
                assert 'id="deliberation-form"' not in detail_html
            token = re.search(r'name="_token" value="([a-f0-9]{64})"', detail_html)
            assert token, 'Policy voting token absent'
            for choice in ('pour', 'contre'):
                vote_data = urlencode({
                    '_token': token.group(1), 'choice': choice,
                }).encode()
                with closing(profile_browser.open(urllib.request.Request(
                    url + detail_path + '/vote', data=vote_data, method='POST',
                    headers={'Origin': url}
                ), timeout=28)) as response:
                    final = response.read().decode('utf-8', 'replace')
                    assert response.status == 200
                    assert 'Position enregistrée.' in final
            # La délibération n'accepte ni commentaires libres ni preuves prétendues sans source.
            with closing(profile_browser.open(url + detail_path, timeout=28)) as response:
                voted_detail = response.read().decode('utf-8', 'replace')
                assert 'L’arbre des causes et conséquences' in voted_detail
                assert 'id="deliberation-form"' in voted_detail
                assert 'Auteur de test' in voted_detail
            causal_token = re.search(
                r'<form id="deliberation-form".*?name="_token" value="([a-f0-9]{64})"',
                voted_detail, re.S
            )
            assert causal_token, 'No post-vote causal-deliberation CSRF token'
            contribution = {
                '_token': causal_token.group(1),
                '_from': 'consultations',
                'parent_id': '',
                'cause_text': 'Des délais administratifs persistants peuvent expliquer la réforme envisagée.',
                'cause_degree': 'demontree',
                'cause_source': 'https://www.senat.fr/dossier-legislatif/ppl26-451.html',
                'consequence_text': 'Le changement proposé pourrait faciliter les démarches dans plusieurs collectivités.',
                'consequence_degree': 'probable',
                'consequence_source': '',
            }
            bad = contribution | {'cause_source': ''}
            try:
                profile_browser.open(urllib.request.Request(
                    url + detail_path + '/deliberer', data=urlencode(bad).encode(),
                    method='POST', headers={'Origin': url}
                ), timeout=28)
                raise AssertionError('Unsourced allegedly demonstrated cause accepted')
            except urllib.error.HTTPError as error:
                assert error.code == 400, ('Causal proof', error.code)
                error.close()
            with closing(profile_browser.open(urllib.request.Request(
                url + detail_path + '/deliberer', data=urlencode(contribution).encode(),
                method='POST', headers={'Origin': url}
            ), timeout=28)) as response:
                causal_html = response.read().decode('utf-8', 'replace')
                assert response.status == 200
                assert 'Votre analyse causale a été publiée.' in causal_html
                assert 'Des délais administratifs persistants' in causal_html
                assert 'id="deliberation-form"' in causal_html
            with sqlite3.connect(temporary_catalog) as catalog_db:
                first_branch = catalog_db.execute(
                    "SELECT id FROM policy_deliberations ORDER BY rowid DESC LIMIT 1"
                ).fetchone()[0]
                assert first_branch.startswith('pc_')
                assert catalog_db.execute(
                    "SELECT COUNT(*) FROM policy_deliberations"
                ).fetchone()[0] == 1
            reply = contribution | {
                'parent_id': first_branch,
                'cause_degree': 'observee',
                'cause_source': '',
                'consequence_degree': 'probable',
            }
            with closing(profile_browser.open(urllib.request.Request(
                url + detail_path + '/deliberer', data=urlencode(reply).encode(),
                method='POST', headers={'Origin': url}
            ), timeout=28)) as response:
                assert response.status == 200
                assert '2 contribution(s) structurée(s)' in response.read().decode(
                    'utf-8', 'replace'
                )
            with sqlite3.connect(temporary_catalog) as catalog_db:
                linked = catalog_db.execute(
                    "SELECT parent_id, depth FROM policy_deliberations "
                    "ORDER BY rowid DESC LIMIT 1"
                ).fetchone()
                assert linked == (first_branch, 1), ('Threaded causal response', linked)
            with closing(profile_browser.open(
                url + detail_path + '?from=consultations&edit=' + first_branch, timeout=28
            )) as response:
                edit_html = response.read().decode('utf-8', 'replace')
                assert response.status == 200
                assert 'id="revision-form"' in edit_html
                assert 'Modifier mes textes' in edit_html
                assert 'Des délais administratifs persistants' in edit_html
            edit_token = re.search(
                r'<form id="revision-form".*?name="_token" value="([a-f0-9]{64})"',
                edit_html, re.S
            )
            assert edit_token, 'Author-only causal revision token unavailable'
            correction = contribution | {
                '_token': edit_token.group(1),
                '_from': 'consultations',
                'expected_version': '1',
                'cause_text': 'La cause initiale est corrigée pour prendre en compte des données nouvelles.',
                'cause_degree': 'observee',
                'cause_source': '',
                'consequence_text': 'La nouvelle conséquence doit encore être mesurée après la réforme proposée.',
            }
            with closing(profile_browser.open(urllib.request.Request(
                url + detail_path + '/deliberations/' + first_branch + '/modifier',
                data=urlencode(correction).encode(),
                method='POST', headers={'Origin': url}
            ), timeout=28)) as response:
                history_html = response.read().decode('utf-8', 'replace')
                assert response.status == 200
                assert 'l’ancienne reste consultable' in history_html
                assert 'Version 2' in history_html
                assert 'Version 1' in history_html
                assert 'Des délais administratifs persistants' in history_html
                assert 'La cause initiale est corrigée' in history_html
                assert 'id="causal-history"' in history_html
            try:
                profile_browser.open(urllib.request.Request(
                    url + detail_path + '/deliberations/' + first_branch + '/modifier',
                    data=urlencode(correction).encode(),
                    method='POST', headers={'Origin': url}
                ), timeout=28)
                raise AssertionError('Stale causal revision unexpectedly accepted')
            except urllib.error.HTTPError as error:
                assert error.code == 409, ('Stale causal revision', error.code)
                error.close()
            with sqlite3.connect(temporary_catalog) as catalog_db:
                rows = catalog_db.execute(
                    "SELECT version, cause_text FROM policy_deliberation_revisions "
                    "WHERE entry_id = ? ORDER BY version", (first_branch,)
                ).fetchall()
                assert len(rows) == 2
                assert rows[0][0] == 1 and rows[1][0] == 2
                assert rows[0][1] != rows[1][1]
                assert catalog_db.execute(
                    "SELECT COUNT(*) FROM policy_deliberations"
                ).fetchone()[0] == 2, 'Editing created an extra causal branch'
            print('PASS causal revision timeline: original version retained and stale edit rejected', flush=True)
            print('PASS post-vote causal tree: blocked unsourced proofs, two linked branches', flush=True)

            with closing(profile_browser.open(
                url + '/consultations?status=open&participation=voted', timeout=28
            )) as response:
                dashboard = response.read().decode('utf-8', 'replace')
                assert response.status == 200
                assert 'Votre position : Contre' in dashboard
                assert 'Voter sur les propositions de loi' in dashboard
            with closing(profile_browser.open(
                url + '/consultations?status=open&participation=pending', timeout=28
            )) as response:
                pending = response.read().decode('utf-8', 'replace')
                assert 'Aucune consultation correspondante' in pending

            with sqlite3.connect(temporary_catalog) as catalog_db:
                id_value = detail_path.rsplit('/', 1)[-1]
                assert catalog_db.execute(
                    "SELECT COUNT(*) FROM policy_votes WHERE measure_id = ?",
                    (id_value,),
                ).fetchone()[0] == 1, 'More than one active vote from one beta profile'
                assert catalog_db.execute(
                    "SELECT COUNT(*) FROM policy_vote_history WHERE measure_id = ?",
                    (id_value,),
                ).fetchone()[0] == 2, 'Policy vote revision history missing'
                catalog_db.execute(
                    "UPDATE policy_measures SET closes_at = ? WHERE id = ?",
                    ('2026-01-01T00:00:00+00:00', id_value),
                )
                catalog_db.commit()
            with closing(profile_browser.open(url + detail_path, timeout=28)) as response:
                closed_html = response.read().decode('utf-8', 'replace')
                assert 'Consultation clôturée : résultat' in closed_html
                assert 'Auteur de test' in closed_html
                assert 'Contre :' in closed_html
                assert 'Enregistrer ma position fictive' not in closed_html
            with closing(profile_browser.open(
                url + '/consultations?status=closed', timeout=28
            )) as response:
                closed_list = response.read().decode('utf-8', 'replace')
                assert 'Clôturée' in closed_list
                assert 'Voir les résultats' in closed_list
            print('PASS official bill catalogue: source filtered, beta vote, revision and disclosure', flush=True)

            # Existing accounts must not reach the obsolete fictional-persona creator.
            with closing(profile_browser.open(url + '/citizen', timeout=28)) as response:
                citizen_html = response.read().decode('utf-8', 'replace')
                assert response.status == 200
                assert 'Compte de test actif' in citizen_html
                assert 'Alex Fictif' in citizen_html
                assert 'Comptes de test dans le laboratoire partagé' in citizen_html
                assert 'Créer un personnage fictif (laboratoire)' not in citizen_html
            with closing(profile_browser.open(url + '/action/citizen.create', timeout=28)) as response:
                assert response.status == 200
                assert response.geturl().split('?', 1)[0] == url + '/compte-test'
            try:
                profile_browser.open(urllib.request.Request(
                    url + '/action/citizen.create', data=b'no-extra-account=1', method='POST'
                ), timeout=28)
                raise AssertionError('An active beta account reached the legacy character creator')
            except urllib.error.HTTPError as error:
                assert error.code == 403, ('Legacy persona creation', error.code)
                error.close()
            print('PASS active beta participant and legacy persona creation refused', flush=True)


            # A municipality outside the old pilot fixtures must have an isolated local workspace.
            import re
            from urllib.parse import urlencode
            with closing(browser.open(url + '/', timeout=28)) as response:
                initial_html = response.read(5_000_000).decode('utf-8', 'replace')
            selector = re.search(r'<form[^>]*action="/territoire/choisir"[^>]*>.*?</form>', initial_html, re.S)
            assert selector, 'Shared territory selector missing'
            token = re.search(r'name="_token" value="([^"]+)"', selector.group(0))
            assert token, 'Territory CSRF token missing'
            posted = urlencode({'_token': token.group(1), 'code': '66134',
                                'code_type': 'insee', 'destination': 'budget'}).encode()
            with closing(browser.open(urllib.request.Request(url + '/territoire/choisir', data=posted, method='POST'), timeout=28)) as response:
                assert response.status == 200
                local_html = response.read(5_000_000).decode('utf-8', 'replace')
            assert 'Passa' in local_html, 'INSEE municipality not selected'
            assert 'Pas de scénario local' not in local_html, 'Legacy three-city gate still active'
            for path in ('/consultations', '/experiments', '/services', '/budget', '/action/proposal.create'):
                with closing(browser.open(url + path, timeout=28)) as response:
                    assert response.status == 200, (path, response.status)
                    local_html = response.read(5_000_000).decode('utf-8', 'replace')
                assert 'Pas de scénario local' not in local_html, path
                print('PASS selected INSEE 66134 ' + path, flush=True)
            print("PASS: real fresh-install HTTP smoke", flush=True)
        except BaseException:
            logs.flush()
            logs.seek(0)
            print("===== PHP built-in server log =====", file=sys.stderr)
            print(logs.read()[-20000:], file=sys.stderr)
            raise
        finally:
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait()


if __name__ == "__main__":
    main()
