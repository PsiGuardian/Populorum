#!/usr/bin/env python3
"""Import precisely sourced public procurement statements (offline, no scraping).

Usage: python3 tools/import_procurement_evidence.py dossier.json --db data/research/public-procurement-v2-staging.sqlite
Each statement MUST link to its real, exact document URL. No inferred claim is
published as a documentary fact. A BOAMP search candidate is not documentary proof.
"""
from __future__ import annotations

import argparse
from datetime import datetime, timezone
import hashlib
import ipaddress
import json
from pathlib import Path
import re
import sqlite3
from urllib.parse import urlsplit

BASE = Path(__file__).resolve().parents[1]
ALLOWED_CATEGORIES = ('context', 'purpose', 'scope', 'selection', 'funding', 'payment', 'outcome')
ALLOWED_STATUS = ('candidate', 'documented')
ALLOWED_SOURCE_TYPES = ('boamp', 'ted', 'cctp', 'deliberation', 'budget', 'accounting', 'evaluation', 'other')


def safe_url(value: str) -> bool:
    if not isinstance(value, str) or len(value) > 2048 or any(ord(c) < 32 for c in value):
        return False
    try:
        url = urlsplit(value)
        if url.scheme != 'https' or not url.hostname or url.username or url.password:
            return False
        hostname = url.hostname.rstrip('.').lower()
        if hostname in ('localhost',) or hostname.endswith(('.localhost', '.local', '.internal')):
            return False
        try:
            if not ipaddress.ip_address(hostname).is_global:
                return False
        except ValueError:
            if '.' not in hostname or any(not re.fullmatch(r'[a-z0-9-]+', x) for x in hostname.split('.')):
                return False
        return True
    except ValueError:
        return False


def check_text(row: dict, key: str, max_len: int, required: bool = True) -> str:
    val = row.get(key, '')
    if not isinstance(val, str) or len(val) > max_len or (required and len(val.strip()) < 2):
        raise ValueError(f'Champ documentaire invalide : {key}')
    return val.strip()


def import_evidence(db: sqlite3.Connection, entries: list) -> int:
    version = db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
    if version != ('populorum-decp-2',):
        raise ValueError('Seule une base DECP v2 reconstruite peut recevoir des références.')
    count = 0
    for row in entries:
        if not isinstance(row, dict):
            raise ValueError('Chaque preuve doit être un objet.')
        key = check_text(row, 'contract_key', 40)
        if not re.fullmatch(r'[a-f0-9]{40}', key):
            raise ValueError('Clé de contrat invalide.')
        if not db.execute('SELECT 1 FROM contracts WHERE contract_key=?', (key,)).fetchone():
            raise ValueError('Contrat inexistant : ' + key)
        category = check_text(row, 'category', 24)
        source_type = check_text(row, 'source_type', 30)
        status = check_text(row, 'status', 20)
        if category not in ALLOWED_CATEGORIES or source_type not in ALLOWED_SOURCE_TYPES or status not in ALLOWED_STATUS:
            raise ValueError('Nature, type de source ou statut inconnu.')
        url = check_text(row, 'source_url', 2048)
        if not safe_url(url):
            raise ValueError('URL de source dangereuse ou invalide.')
        title = check_text(row, 'document_title', 300)
        origin = check_text(row, 'source_name', 140)
        statement = check_text(row, 'statement', 1200, status == 'documented')
        excerpt = check_text(row, 'excerpt', 600, status == 'documented')
        if status == 'candidate' and statement:
            raise ValueError('Un document candidat ne peut pas porter une affirmation validée.')
        published = check_text(row, 'published_at', 32, False)
        if published and not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}', published):
            raise ValueError('Date de publication attendue AAAA-MM-JJ ou vide.')
        identity = json.dumps([key, category, url, statement, excerpt], ensure_ascii=False, separators=(',', ':'))
        evidence_id = hashlib.sha256(identity.encode()).hexdigest()
        cur = db.execute('''INSERT OR IGNORE INTO procurement_evidence
            (evidence_id,contract_key,category,source_type,source_name,source_url,
             document_title,statement,excerpt,published_at,recorded_at,status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)''',
            (evidence_id,key,category,source_type,origin,url,title,statement,excerpt,
             published,datetime.now(timezone.utc).isoformat(timespec='seconds'),status))
        count += cur.rowcount
    return count


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('file', type=Path, help='Tableau JSON de sources documentaires')
    parser.add_argument('--db', type=Path, default=BASE/'data/research/public-procurement-v2-staging.sqlite')
    args = parser.parse_args()
    entries = json.loads(args.file.read_text(encoding='utf-8'))
    if not isinstance(entries, list) or len(entries) > 10000:
        raise ValueError('Le fichier doit être une liste (maximum 10 000 éléments).')
    with sqlite3.connect(args.db) as db:
        db.execute('BEGIN IMMEDIATE')
        try:
            n = import_evidence(db, entries)
            db.commit()
        except BaseException:
            db.rollback()
            raise
    print(f'{n} nouvelles références documentaires ajoutées ; {len(entries)-n} déjà présentes.')


if __name__ == '__main__':
    main()
