#!/usr/bin/env python3
"""Enrichir sans altérer les votes du catalogue : dossiers et documents du Sénat.

Téléchargement explicite, incrémental et limité. Seuls les liens officiels présents
dans les dossiers sont retenus ; pas de document ou de résumé inventé.
"""
from __future__ import annotations

import argparse
from datetime import datetime, timezone
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import sqlite3
import sys
import time
from urllib.error import HTTPError, URLError
from urllib.parse import urldefrag, urljoin, urlparse
from urllib.request import HTTPRedirectHandler, Request, build_opener

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DB = ROOT / "var" / "policy-catalog.sqlite"
MAX_HTML = 2_000_000
GROUPS = {
    "expose": "Exposé des motifs",
    "texte": "Texte déposé ou adopté",
    "commission": "Texte ou travaux de commission",
    "rapport": "Rapport parlementaire",
    "amendement": "Amendements",
    "debat": "Débats et comptes rendus de séance",
    "synthese": "Synthèse ou résumé parlementaire",
    "procedure": "Parcours législatif et textes connexes",
    "assemblee": "Navette vers l'Assemblée nationale",
    "autre": "Autres ressources du dossier",
}


def official(url: str) -> bool:
    p = urlparse(url)
    if p.scheme != "https" or not p.hostname or p.username or p.password:
        return False
    h = p.hostname.lower()
    return (
        h == "senat.fr" or h.endswith(".senat.fr")
        or h == "assemblee-nationale.fr"
        or h.endswith(".assemblee-nationale.fr")
    )


def dossier_url(url: str) -> bool:
    p = urlparse(url)
    return (official(url) and p.hostname in ("www.senat.fr", "senat.fr")
            and bool(re.fullmatch(r"/dossier-legislatif/[\w.-]+\.html", p.path))
            and len(url) <= 400)


def direct_report(url: str) -> bool:
    """True for a numbered report file, never for the Senate-wide report index.

    Real links have a report directory and an individual HTML/PDF file,
    e.g. /rap/l22-376/l22-376.html or /rap/r20-760/r20-760_mono.html.
    """
    if not official(url):
        return False
    parsed = urlparse(url)
    if parsed.hostname not in ("senat.fr", "www.senat.fr"):
        return False
    match = re.fullmatch(
        r"/rap/([a-z]{1,3}[0-9]{2}-[0-9]+)/([^/]+)\.(?:html|pdf)",
        parsed.path, flags=re.IGNORECASE,
    )
    return bool(match and match.group(2).lower().startswith(match.group(1).lower()))


def classify(label: str, url: str) -> str | None:
    p = urlparse(url)
    path = p.path.lower()
    label = " ".join(label.lower().split())
    if "/leg/exposes-des-motifs/" in path:
        return "expose"
    if "assemblee-nationale.fr" in (p.hostname or ""):
        return "assemblee" if "dossier" in label or "texte" in label else None
    if direct_report(url):
        return "rapport"
    if "amendement" in label or "/amendements/" in path:
        return "amendement"
    if ("compte rendu" in label or "compte-rendu" in path
            or "réunions" in label or "reunions" in label):
        return "debat" if ("séance" in label or "seance" in label
                           or "/seances/" in path) else "commission"
    if "débat" in label or "debat" in label or "/seances/" in path:
        return "debat"
    if "la loi en construction" in label or "procédure" in label:
        return "procedure"
    if "l'essentiel" in label or "résumé des débats" in label:
        return "synthese"
    if "/leg/" in path and ("texte" in label or "/leg/ppl" in path
                            or "/leg/tas" in path):
        return "commission" if "commission" in label else "texte"
    if "texte de la commission" in label:
        return "commission"
    if "loi promulguée" in label or "loi promulguee" in label:
        return "procedure"
    return None


class SenateHTML(HTMLParser):
    """Extract source-linked document titles and bounded original motives."""

    def __init__(self, base_url: str):
        super().__init__(convert_charrefs=True)
        self.base_url = base_url
        self.links: list[tuple[str, str, str]] = []
        self.paragraphs: list[str] = []
        self.heading = ""
        self._h_level: str | None = None
        self._h_parts: list[str] = []
        self._a_parts: list[str] | None = None
        self._a_href: str | None = None
        self._p_depth = 0
        self._p_parts: list[str] = []
        self._motive_started = False
        self._motive_paragraphs: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attrs_dict = dict(attrs)
        if tag in {"h1", "h2", "h3", "h4", "h5"}:
            self._h_level = tag
            self._h_parts = []
        if tag == "a":
            self._a_href = attrs_dict.get("href")
            self._a_parts = []
        if tag == "p":
            if self._p_depth == 0:
                self._p_parts = []
            self._p_depth += 1

    def handle_data(self, data: str) -> None:
        if self._h_level:
            self._h_parts.append(data)
        if self._a_parts is not None:
            self._a_parts.append(data)
        if self._p_depth > 0:
            self._p_parts.append(data)
        if data.strip() == "EXPOSÉ DES MOTIFS":
            self._motive_started = True

    def handle_endtag(self, tag: str) -> None:
        if tag == "a" and self._a_parts is not None:
            label = " ".join(" ".join(self._a_parts).split())
            if self._a_href and label:
                self.links.append((label, self._a_href, self.heading))
            self._a_parts = None
            self._a_href = None
        if tag in {"h1", "h2", "h3", "h4", "h5"} and self._h_level == tag:
            label = " ".join(" ".join(self._h_parts).split())
            if label:
                self.heading = label[:160]
            self._h_level = None
        if tag == "p" and self._p_depth:
            self._p_depth -= 1
            if self._p_depth == 0:
                para = " ".join(" ".join(self._p_parts).split())
                if para:
                    self.paragraphs.append(para)
                    if self._motive_started and len(para) >= 95:
                        if len(self._motive_paragraphs) < 6:
                            self._motive_paragraphs.append(para)
                self._p_parts = []

    def documents(self) -> list[dict]:
        results: list[dict] = []
        seen: set[str] = set()
        for label, href, section in self.links:
            url = urldefrag(urljoin(self.base_url, href))[0]
            if not official(url) or len(url) > 600:
                continue
            kind = classify(label, url)
            if kind is None or url in seen or url == self.base_url:
                continue
            seen.add(url)
            results.append({
                "kind": kind, "label": label[:220], "url": url,
                "source_section": section[:160],
            })
            if len(results) >= 120:
                break
        return results

    def motives(self) -> list[str]:
        excerpt: list[str] = []
        chars = 0
        for paragraph in self._motive_paragraphs:
            # Never synthesize an explanation: retain original bounded paragraphs.
            if chars + len(paragraph) > 2500:
                break
            excerpt.append(paragraph)
            chars += len(paragraph)
        return excerpt


SCHEMA = """
CREATE TABLE IF NOT EXISTS policy_senate_documents (
    measure_id TEXT NOT NULL REFERENCES policy_measures(id),
    kind TEXT NOT NULL,
    label TEXT NOT NULL,
    url TEXT NOT NULL,
    source_section TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (measure_id, url)
);
CREATE INDEX IF NOT EXISTS idx_policy_senate_document_kind
    ON policy_senate_documents(measure_id, kind);
CREATE TABLE IF NOT EXISTS policy_senate_context (
    measure_id TEXT PRIMARY KEY REFERENCES policy_measures(id),
    excerpt_text TEXT NOT NULL DEFAULT '',
    excerpt_url TEXT NOT NULL DEFAULT '',
    synced_at TEXT NOT NULL
);
"""


class OfficialRedirects(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if not official(newurl):
            raise ValueError("Redirection non institutionnelle refusée")
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def fetch_html(url: str, timeout: float) -> str:
    if not official(url):
        raise ValueError("URL non institutionnelle")
    opener = build_opener(OfficialRedirects())
    req = Request(url, headers={
        "User-Agent": "PopulorumResearch/0.1 (public institutional sources; no account)",
        "Accept": "text/html,application/xhtml+xml",
    })
    with opener.open(req, timeout=timeout) as resp:
        if not official(resp.geturl()):
            raise ValueError("Redirection non institutionnelle")
        media_type = resp.headers.get_content_type()
        if media_type not in ("text/html", "application/xhtml+xml"):
            raise ValueError("Document non HTML")
        payload = resp.read(MAX_HTML + 1)
        if len(payload) > MAX_HTML:
            raise ValueError("Page trop volumineuse")
        charset = resp.headers.get_content_charset() or "utf-8"
        try:
            return payload.decode(charset)
        except (UnicodeDecodeError, LookupError):
            return payload.decode("utf-8", "replace")


def extract(base_url: str, html: str, *, fetch_motives=None) -> tuple[list[dict], list[str], str]:
    parser = SenateHTML(base_url)
    parser.feed(html)
    documents = parser.documents()
    excerpts: list[str] = []
    excerpt_url = ""
    exposes = [d for d in documents if d["kind"] == "expose"]
    if exposes and fetch_motives is not None:
        source = exposes[0]
        detail = SenateHTML(source["url"])
        detail.feed(fetch_motives(source["url"]))
        excerpts = detail.motives()
        if excerpts:
            excerpt_url = source["url"]
    return documents, excerpts, excerpt_url


def store(conn: sqlite3.Connection, measure_id: str, documents: list[dict],
          excerpts: list[str], excerpt_url: str) -> None:
    synced_at = datetime.now(timezone.utc).isoformat(timespec="seconds")
    with conn:
        conn.execute("DELETE FROM policy_senate_documents WHERE measure_id = ?", (measure_id,))
        conn.executemany(
            "INSERT INTO policy_senate_documents "
            "(measure_id, kind, label, url, source_section, sort_order) "
            "VALUES (?, ?, ?, ?, ?, ?)",
            [(measure_id, d["kind"], d["label"], d["url"], d["source_section"], i)
             for i, d in enumerate(documents)],
        )
        conn.execute(
            "INSERT INTO policy_senate_context "
            "(measure_id, excerpt_text, excerpt_url, synced_at) VALUES (?, ?, ?, ?) "
            "ON CONFLICT(measure_id) DO UPDATE SET "
            "excerpt_text = excluded.excerpt_text, excerpt_url = excluded.excerpt_url, "
            "synced_at = excluded.synced_at",
            (measure_id, "\n\n".join(excerpts), excerpt_url, synced_at),
        )


def run(db_path: Path, limit: int, offset: int, pause: float, timeout: float,
        force: bool = False) -> dict:
    if limit < 1 or limit > 2000 or offset < 0 or not (0 <= pause <= 30):
        raise ValueError("Lot : 1–2000 dossiers, décalage positif, pause 0–30 secondes.")
    if not db_path.is_file():
        raise FileNotFoundError("Catalogue absent : importer les propositions de loi en premier.")
    conn = sqlite3.connect(db_path, timeout=20)
    try:
        conn.execute("PRAGMA foreign_keys=ON")
        conn.execute("PRAGMA busy_timeout=20000")
        conn.executescript(SCHEMA)
        rows = conn.execute(
            "SELECT id, source_url FROM policy_measures "
            "WHERE source_type = 'proposition_de_loi' "
            "ORDER BY id LIMIT ? OFFSET ?",
            (limit, offset),
        ).fetchall()
        stats = {"selected": len(rows), "updated": 0, "already_cached": 0,
                 "failed": 0, "documents": 0, "motives_excerpts": 0}
        for id_value, url in rows:
            if not dossier_url(url):
                stats["failed"] += 1
                print("IGNORÉ : URL de dossier non reconnue", file=sys.stderr)
                continue
            exists = conn.execute(
                "SELECT 1 FROM policy_senate_context WHERE measure_id = ?", (id_value,)
            ).fetchone()
            if exists and not force:
                stats["already_cached"] += 1
                continue
            try:
                dossier_html = fetch_html(url, timeout)
                # The exposition is fetched separately and only from the
                # exact institutional URL discovered in the dossier itself.
                def fetch_motives(expose_url):
                    if pause:
                        time.sleep(pause)
                    return fetch_html(expose_url, timeout)
                docs, paragraphs, excerpt_url = extract(
                    url, dossier_html, fetch_motives=fetch_motives
                )
                if not docs:
                    raise ValueError("Aucun document institutionnel reconnu : cache préservé")
                store(conn, id_value, docs, paragraphs, excerpt_url)
                stats["updated"] += 1
                stats["documents"] += len(docs)
                stats["motives_excerpts"] += len(paragraphs)
            except (OSError, ValueError, HTTPError, URLError) as error:
                stats["failed"] += 1
                print(f"ÉCHEC {id_value}: {error}", file=sys.stderr)
            if pause:
                time.sleep(pause)
        return stats
    finally:
        conn.close()


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--db", type=Path, default=DEFAULT_DB)
    parser.add_argument("--limit", type=int, default=20,
                        help="Nombre de dossiers par lot (maximum 2000)")
    parser.add_argument("--offset", type=int, default=0,
                        help="Décalage de reprise d'un lot")
    parser.add_argument("--pause", type=float, default=0.6,
                        help="Secondes entre les accès au Sénat")
    parser.add_argument("--timeout", type=float, default=12,
                        help="Délai maximal de chaque requête en secondes")
    parser.add_argument("--force", action="store_true",
                        help="Actualiser les dossiers déjà enrichis")
    args = parser.parse_args()
    stats = run(args.db, args.limit, args.offset, args.pause, args.timeout, args.force)
    print(json.dumps(stats, ensure_ascii=False, indent=2))
    print("Nouveaux textes explicatifs : extraits d'auteur, non fact-checkés.")
    return 0 if stats["failed"] == 0 else 2


if __name__ == "__main__":
    raise SystemExit(main())
