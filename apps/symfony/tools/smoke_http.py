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
