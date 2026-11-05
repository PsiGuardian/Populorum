#!/usr/bin/env python3
"""Offline v2 regression: no lot collisions, original data and sourced claims."""
from __future__ import annotations
import hashlib
import json
from pathlib import Path
import sqlite3
import sys
import tempfile
import zlib

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools'))
from import_decp import SCHEMA, ensure_fts, ensure_history_schema, import_file, contract_key
from import_procurement_evidence import import_evidence, safe_url


def record(title, amount):
    return {'id':'202424A101000000', 'acheteur':{'id':'26680090300012'},
        'nature':'Marché','objet':title,'codeCPV':'33140000-0',
        'dateNotification':'2024-01-22','datePublicationDonnees':'2024-04-02',
        'montant':amount, 'ccag':'Fournitures courantes et services',
        'titulaires':[{'titulaire':{'id':'38360059000038','typeIdentifiant':'SIRET'}}]}


def main():
    a=record('Lot 2 - Sonde de remplacement pour gastrostomie',27362.5)
    b=record('Lot 3 - Kit de bouton de gastrostomie',10708.0)
    assert contract_key(a,'market') != contract_key(b,'market')
    with tempfile.TemporaryDirectory(prefix='populorum-decp-v2-') as folder:
        root=Path(folder)
        source=root/'decp-2024-04.json'
        source.write_text(json.dumps({'marches':{'marche':[a,b],'contrat-concession':[]}},ensure_ascii=False),encoding='utf-8')
        with sqlite3.connect(root/'test.sqlite') as db:
            db.executescript(SCHEMA)
            ensure_fts(db)
            ensure_history_schema(db)
            assert import_file(db,source,'https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides')
            assert db.execute('SELECT COUNT(*) FROM contracts').fetchone()[0]==2
            originals=db.execute('SELECT record_sha,record_zlib FROM contract_records ORDER BY ordinal').fetchall()
            assert len(originals)==2
            for digest,compressed in originals:
                raw=zlib.decompress(compressed)
                assert hashlib.sha256(raw).hexdigest()==digest
                assert json.loads(raw)['ccag']=='Fournitures courantes et services'
            assert not import_file(db,source)
            assert db.execute('SELECT COUNT(*) FROM contract_records').fetchone()[0]==2
            key=contract_key(a,'market')
            evidence=[{'contract_key':key,'category':'purpose','source_type':'deliberation',
                'source_name':'Collectivité fictive pour test','source_url':'https://example.org/document/123',
                'document_title':'Délibération de test','statement':'Motivation documentée pour le test',
                'excerpt':'Le besoin figure dans la délibération publiée.',
                'published_at':'2024-02-01','status':'documented'}]
            assert import_evidence(db,evidence)==1
            assert import_evidence(db,evidence)==0
            assert db.execute('SELECT COUNT(*) FROM procurement_evidence').fetchone()[0]==1
            assert not safe_url('http://127.0.0.1/admin')
            assert not safe_url('https://localhost/private')
            assert not safe_url('javascript:alert(1)')
            candidate=[dict(evidence[0],status='candidate',statement='Affirmation sans preuve')]
            try:
                import_evidence(db,candidate)
            except ValueError:pass
            else:raise AssertionError('An unverified candidate carried an assertion')
            assert db.execute('PRAGMA quick_check').fetchone()[0]=='ok'
    print('PASS v2: 2 lots distincts, 2 enregistrements SHA-256 originaux et preuves sourcées strictes.')


if __name__=='__main__':
    main()
