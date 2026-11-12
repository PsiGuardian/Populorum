#!/usr/bin/env python3
"""Test financial-document import without external files, network, or live databases."""
from __future__ import annotations
from pathlib import Path
import sqlite3
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools'))
from import_decp import SCHEMA
from import_procurement_financial_evidence import (
    SCHEMA as FINANCIAL_SCHEMA, SCHEMA_VERSION, import_entries, money, normalize
)


def document(key: str, stage: str = 'paid', amount: str = '1234.50') -> dict:
    return {
        'contract_key': key, 'stage': stage, 'amount_eur': amount,
        'scope': 'cumulative', 'as_of_date': '2026-11-01',
        'period_start': '', 'period_end': '',
        'document_date': '2026-11-02',
        'source_issuer': 'Organisme fictif de test',
        'document_title': 'État financier fictif de test',
        'source_url': 'https://example.org/documents/contrat-test-2026',
        'source_excerpt': 'Extrait fictif, montant et périmètre explicitement identifiés.',
    }


def raises(fn, label: str) -> None:
    try:
        fn()
    except ValueError:
        return
    raise AssertionError('Valeur acceptée à tort : ' + label)


def main():
    k1 = 'a' * 40
    k2 = 'b' * 40
    with sqlite3.connect(':memory:') as national, sqlite3.connect(':memory:') as evidence:
        national.executescript(SCHEMA)
        national.execute("INSERT INTO metadata VALUES ('schema','populorum-decp-2')")
        for key, obj in ((k1, 'Lot 1'), (k2, 'Lot 2')):
            national.execute('''INSERT INTO contracts
                (contract_key,kind,buyer_id,original_id,cpv,nature,object,
                procedure,notice_date,published_date,location_type,location_code,
                source_name,source_sha,source_snapshot)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''',
                (key,'market','26380030200014','AO25DMISP','33140000','Marché',obj,
                 'ouverte','2025-05-20','2026-01-01','Code département','38',
                 'source','sha','decp-2026-01.json'))
        national.commit()
        evidence.executescript(FINANCIAL_SCHEMA)
        first = document(k1)
        assert money('1234.50') == 123450
        assert money('99999999999.00') == 9999999999900
        for value in (1234.5, '1.123', '-1', '1e9', '1 000', 'nan', ''):
            raises(lambda v=value: money(v), 'amount: ' + repr(value))
        raises(lambda: normalize(dict(first, source_url='http://127.0.0.1/document')), 'unsafe URL')
        raises(lambda: normalize(dict(first, source_excerpt='')), 'unsourced number')
        raises(lambda: normalize(dict(first, stage='unknown')), 'unknown stage')
        raises(lambda: normalize(dict(first, as_of_date='2026-02-30')), 'invalid date')
        raises(lambda: normalize(dict(first, scope='period')), 'missing period')
        raises(lambda: normalize(document('z' * 40)), 'bad contract key')
        assert import_entries(evidence, national, [first]) == 1
        assert import_entries(evidence, national, [first]) == 0
        # Distinct stages and lots must NOT be merged, even if they share an
        # original public procedure ID and the same buyer.
        assert import_entries(evidence, national, [document(k1,'invoiced','1450.00'),
                                                    document(k2,'paid','150.00')]) == 2
        rows = evidence.execute('SELECT contract_key,stage,amount_cents FROM financial_evidence').fetchall()
        assert len(rows) == 3, rows
        assert len([r for r in rows if r[0] == k1]) == 2
        assert len([r for r in rows if r[0] == k2]) == 1
        assert evidence.execute("SELECT value FROM metadata WHERE key='schema'").fetchone() == (SCHEMA_VERSION,)
        assert national.execute('SELECT COUNT(*) FROM contracts').fetchone()[0] == 2
        assert national.execute('PRAGMA quick_check').fetchone()[0] == 'ok'
        raises(lambda: import_entries(evidence,national,[document('c'*40)]),'missing contract')
    print('PASS: financial evidence optional, exact cents, strict sources, 2 lots preserved, immutable DECP.')


if __name__ == '__main__':
    main()
