#!/usr/bin/env python3
"""Import evidence-backed financial amounts into an INDEPENDENT optional SQLite file.

No invented amounts, no screen scraping, no writes to national DECP, no sums.
Each entry requires an exact HTTPS document URL, its quote, stage, perimeter and date.

Usage, from apps/symfony:
  python3 tools/import_procurement_financial_evidence.py documents.json
  python3 tools/import_procurement_financial_evidence.py documents.json --db /path/evidence.sqlite

This tool records cited amounts; it does not certify the document contents.
"""
from __future__ import annotations

import argparse
from datetime import date, datetime, timezone
from decimal import Decimal, InvalidOperation
import hashlib
import json
from pathlib import Path
import re
import sqlite3
from import_procurement_evidence import safe_url

ROOT = Path(__file__).resolve().parents[1]
NATIONAL = ROOT / 'data/research/public-procurement.sqlite'
SIDECAR = ROOT / 'data/research/procurement-financial-evidence.sqlite'
SCHEMA_VERSION = 'populorum-procurement-financial-1'
STAGES = ('ceiling', 'committed', 'ordered', 'invoiced', 'paid')
SCOPES = ('cumulative', 'period', 'single')

SCHEMA = """
CREATE TABLE IF NOT EXISTS metadata(key TEXT PRIMARY KEY,value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS financial_evidence(
 evidence_id TEXT PRIMARY KEY,contract_key TEXT NOT NULL,
 stage TEXT NOT NULL CHECK(stage IN ('ceiling','committed','ordered','invoiced','paid')),
 amount_cents INTEGER NOT NULL CHECK(amount_cents>=0),
 accounting_scope TEXT NOT NULL CHECK(accounting_scope IN ('cumulative','period','single')),
 as_of_date TEXT NOT NULL,period_start TEXT NOT NULL,period_end TEXT NOT NULL,
 document_date TEXT NOT NULL,source_issuer TEXT NOT NULL,
 document_title TEXT NOT NULL,source_url TEXT NOT NULL,
 source_excerpt TEXT NOT NULL,recorded_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS financial_by_contract
 ON financial_evidence(contract_key,stage,as_of_date DESC);
"""


def required_text(row: dict, name: str, max_length: int) -> str:
    v = row.get(name)
    if not isinstance(v, str) or not 2 <= len(v.strip()) <= max_length:
        raise ValueError('Valeur documentaire obligatoire : ' + name)
    return v.strip()


def official_date(value: object, name: str, optional: bool = False) -> str:
    if optional and (value is None or value == ''):
        return ''
    if not isinstance(value, str) or not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}', value):
        raise ValueError('Date AAAA-MM-JJ invalide : ' + name)
    try:
        date.fromisoformat(value)
    except ValueError as exc:
        raise ValueError('Date impossible : ' + name) from exc
    return value


def money(value: object) -> int:
    # JSON numeric float is refused: it loses the exact decimal cent value.
    if not isinstance(value, str) or re.fullmatch(r'(?:0|[1-9][0-9]{0,13})(?:\.[0-9]{1,2})?', value) is None:
        raise ValueError('amount_eur : décimal positif en chaîne de caractères, au plus deux décimales')
    try:
        d = Decimal(value) * 100
    except InvalidOperation as exc:
        raise ValueError('amount_eur invalide') from exc
    if d > Decimal('9223372036854775807'):
        raise ValueError('Dépassement de la capacité INTEGER de SQLite')
    return int(d)


def normalize(row: dict) -> tuple:
    if not isinstance(row, dict):
        raise ValueError('Une pièce financière doit être un objet JSON')
    key = row.get('contract_key')
    if not isinstance(key, str) or re.fullmatch(r'[a-f0-9]{40}', key) is None:
        raise ValueError('Clé de contrat invalide')
    stage = row.get('stage')
    if stage not in STAGES:
        raise ValueError('Étape financière inconnue')
    scope = row.get('scope')
    if scope not in SCOPES:
        raise ValueError('Périmètre inconnu')
    amount = money(row.get('amount_eur'))
    as_of = official_date(row.get('as_of_date'), 'as_of_date')
    start = official_date(row.get('period_start'), 'period_start', optional=True)
    end = official_date(row.get('period_end'), 'period_end', optional=True)
    doc_date = official_date(row.get('document_date'), 'document_date')
    if (start == '') != (end == '') or (scope == 'period' and start == '') or (start and start > end):
        raise ValueError('Période incohérente : bornes requises ensemble, et obligatoires pour scope=period')
    url = required_text(row, 'source_url', 2048)
    if not safe_url(url):
        raise ValueError('URL HTTPS publique de document obligatoire')
    issuer = required_text(row, 'source_issuer', 160)
    title = required_text(row, 'document_title', 320)
    excerpt = required_text(row, 'source_excerpt', 1200)
    values = (key, stage, amount, scope, as_of, start, end, doc_date,
              issuer, title, url, excerpt)
    digest = hashlib.sha256(json.dumps(values, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
    return (digest, *values, datetime.now(timezone.utc).isoformat(timespec='seconds'))


def import_entries(financial: sqlite3.Connection, national: sqlite3.Connection, rows: list) -> int:
    existing = financial.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
    if existing not in (None, (SCHEMA_VERSION,)):
        raise ValueError('Schéma financier incompatible')
    national_schema = national.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
    if national_schema != ('populorum-decp-2',):
        raise ValueError('Index DECP national v2 obligatoire, en lecture seule')
    count = 0
    for row in rows:
        record = normalize(row)
        if national.execute('SELECT 1 FROM contracts WHERE contract_key=?', (record[1],)).fetchone() is None:
            raise ValueError('Contrat absent du registre national : ' + record[1])
        cur = financial.execute('''INSERT OR IGNORE INTO financial_evidence
          (evidence_id,contract_key,stage,amount_cents,accounting_scope,
           as_of_date,period_start,period_end,document_date,source_issuer,
           document_title,source_url,source_excerpt,recorded_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)''', record)
        count += cur.rowcount
    financial.execute("INSERT OR REPLACE INTO metadata VALUES ('schema',?)", (SCHEMA_VERSION,))
    return count


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('file', type=Path, help='Liste JSON de montants sourcés')
    parser.add_argument('--decp', type=Path, default=NATIONAL)
    parser.add_argument('--db', type=Path, default=SIDECAR)
    args = parser.parse_args()
    if args.decp.resolve() == args.db.resolve():
        parser.error('La base de preuves doit être séparée de la base DECP')
    entries = json.loads(args.file.read_text(encoding='utf-8'))
    if not isinstance(entries, list) or not 1 <= len(entries) <= 10000:
        parser.error('Liste de 1 à 10 000 documents attendue')
    if not args.decp.is_file():
        parser.error('Base nationale DECP absente')
    args.db.parent.mkdir(parents=True, exist_ok=True)
    with sqlite3.connect(f'file:{args.decp.resolve()}?mode=ro', uri=True) as national:
        with sqlite3.connect(args.db, timeout=30) as financial:
            financial.execute('PRAGMA busy_timeout=30000')
            financial.execute('BEGIN IMMEDIATE')
            try:
                financial.executescript(SCHEMA)
                count = import_entries(financial, national, entries)
                financial.commit()
            except BaseException:
                financial.rollback()
                raise
    print(f'{count} nouvelle(s) pièce(s) chiffrée(s), {len(entries)-count} déjà indexée(s).')
    print('Document source cité : examen humain nécessaire ; aucun paiement inventé.')


if __name__ == '__main__':
    main()
