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
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
ROUTES = (
    ("/", ("Démos Kratos", "Du problème aux conséquences")),
    ("/proposals", ("chemin",)),
    ("/budget", ("Budget",)),
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
        environment = dict(os.environ)
        environment.update(APP_ENV="demo", APP_DEBUG="0", POPULORUM_DEMO="1", DATABASE_URL="sqlite:///" + str(temporary_db))
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
                                'code_type': 'insee', 'destination': 'proposals'}).encode()
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
