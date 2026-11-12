#!/usr/bin/env python3
"""Offline tests for BOAMP national streaming import (no network / DECP writes)."""
from __future__ import annotations
import io
import json
from pathlib import Path
import sqlite3
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
from import_boamp_national import (SCHEMA,normalize,notice_sirets,import_jsonl,
    export_url,json_object,SCHEMA_VERSION)
from reconcile_procurement_boamp import classify

def notice(kind='a'):
    return {'idweb':'25-92465','objet':'Aménagement paysager du parc du château de Chessy Lot n°3 : Pièce d’eau et aménagements écologiques',
        'nomacheteur':'Commune de Chessy','titulaire':'Exemple test',
        'dateparution':'2025-08-14',
        'nature_categorise_libelle':'Résultat de marché',
        'contractfolderid':'dossier-1',
        'url_avis':'https://www.boamp.fr/pages/avis/?q=idweb:25-92465',
        'annonce_lie':'25-78808',
        'donnees':{'Organization':{'SIRET':'21770111900018'},
                   'Reference':{'ID':'2025300000000000'},'variation':kind}}

def main():
    row=notice()
    assert normalize(row)['idweb']=='25-92465'
    assert normalize(row)['source_sha'] != normalize(notice('b'))['source_sha']
    assert '21770111900018' in notice_sirets(row)
    assert 'idweb = ' in export_url(None,True)
    assert 'dateparution >=' in export_url('2026-11-01')
    assert json_object({'record':{'fields':row}})==row
    assert normalize({'idweb':'25-92465','url_avis':'https://evil.example/notice'}) is None
    with sqlite3.connect(':memory:') as db:
        db.executescript(SCHEMA)
        db.execute('INSERT INTO metadata VALUES (?,?)',('schema',SCHEMA_VERSION))
        records=b''.join((json.dumps(x,ensure_ascii=False)+'\n').encode() for x in
                         [row,row,notice('b')])
        assert import_jsonl(db,io.BytesIO(records),'https://boamp-datadila.opendatasoft.com/export')[0:2]==(3,2)
        assert db.execute('SELECT COUNT(*) FROM notices').fetchone()[0]==1
        assert db.execute('SELECT COUNT(*) FROM notice_versions').fetchone()[0]==2
        assert db.execute('SELECT COUNT(*) FROM notice_sirets').fetchone()[0]==1
        assert db.execute('SELECT COUNT(*) FROM notice_relations').fetchone()[0]==1
        assert db.execute('SELECT source_sha FROM notices').fetchone()[0]==normalize(notice('b'))['source_sha']
        assert import_jsonl(db,io.BytesIO(records),'https://boamp-datadila.opendatasoft.com/export')[0:2]==(3,0)
        # The versioned archive is documentary history, not repeated new
        # contract rows or a summable monetary ledger.
        assert db.execute('SELECT COUNT(*) FROM notice_versions').fetchone()[0]==2
        d={'contract_key':'a'*40,'buyer_id':'21770111900018',
           'original_id':'2025300000000000','notice_date':'2505-11-27',
           'object':"Pièce d'eau et aménagements écologiques - Aménagement paysager du parc du château de Chessy"}
        candidate=classify(d,notice('b'))
        assert candidate is not None
    print('PASS: BOAMP JSONL streaming, source revisions, idempotency, provenance and DECP untouched.')

if __name__=='__main__':
    main()
