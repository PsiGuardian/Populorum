#!/usr/bin/env python3
"""Verify the public DECP market listings use indexed order, never temp sort.

No user database is read or modified; synthetic SQLite in memory only.
"""
from __future__ import annotations
from pathlib import Path
import sqlite3
import sys

sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
from import_decp import SCHEMA


def plan(db:sqlite3.Connection, sql:str, params:tuple=()) -> list[str]:
    return [str(row[3]) for row in db.execute('EXPLAIN QUERY PLAN '+sql,params)]


def main():
    with sqlite3.connect(':memory:') as db:
        db.executescript(SCHEMA)
        records=[
            (f'{i:040x}','market' if i%7 else 'concession','98765432100013',
             f'DECP-{i}','33140000-0','Marché','Produit '+str(i),
             'Procédure adaptée',f'2024-{i%12+1:02}-{i%28+1:02}',
             '2026-11-01','Code commune' if i%5 else 'Code département',
             '76351' if i%5 else '76','DECP','sha','{}')
            for i in range(7000)
        ]
        db.executemany('''INSERT INTO contracts (
            contract_key,kind,buyer_id,original_id,cpv,nature,object,procedure,
            notice_date,published_date,location_type,location_code,
            source_name,source_sha,source_snapshot
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''', records)
        cases={
            'national':('''SELECT c.contract_key,c.object,c.notice_date FROM contracts c
                ORDER BY c.notice_date DESC,c.contract_key ASC LIMIT 30''',(),
                'contracts_date_desc'),
            'commune':('''SELECT c.contract_key,c.object,c.notice_date FROM contracts c
                WHERE c.location_type=? AND c.location_code=?
                ORDER BY c.notice_date DESC,c.contract_key ASC LIMIT 30''',
                ('Code commune','76351'),'contracts_geo_date_desc'),
            'nature':('''SELECT c.contract_key,c.object,c.notice_date FROM contracts c
                WHERE c.kind=? ORDER BY c.notice_date DESC,c.contract_key ASC LIMIT 30''',
                ('market',),'contracts_kind_date_desc'),
            'related_references':('''SELECT contract_key,object,notice_date FROM contracts
                WHERE buyer_id=? AND kind=? AND original_id=? AND contract_key<>?
                ORDER BY notice_date DESC,contract_key ASC LIMIT 31''',
                ('98765432100013','market','DECP-123','0'*40),'contracts_buyer_reference'),
        }
        for name,(sql,params,expected_index) in cases.items():
            lines=plan(db,sql,params)
            assert any(expected_index in line for line in lines), (name,lines)
            assert not any('USE TEMP B-TREE' in line for line in lines),(name,lines)
            print('OK',name,':','; '.join(lines))


if __name__=='__main__':
    main()
