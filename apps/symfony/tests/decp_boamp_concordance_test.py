#!/usr/bin/env python3
"""Unit tests for offline BOAMP procedural concordance, without network."""
from __future__ import annotations
from pathlib import Path
import sqlite3
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
from reconcile_procurement_boamp import classify, SCHEMA, store, exact_token


def fixture():
    contract={
        'contract_key':'a'*40,'buyer_id':'26380030200014','original_id':'AO25DMISP',
        'object':'Fourniture de dispositifs médicaux implantables et spécialisés'
    }
    notice={
        'idweb':'26-123456','objet':'Fourniture de dispositifs médicaux implantables et spécialisés',
        'url_avis':'https://www.boamp.fr/pages/avis/?q=idweb:26-123456',
        'dateparution':'12 novembre 2026','nature_categorise_libelle':'Résultat de marché',
        'DONNEES':{'acheteur':{'identifiant':'26380030200014'},
                  'procedure':{'reference':'AO25DMISP'},
                  'montant_total_procedure':'880000.00'},
    }
    return contract,notice


def main():
    contract,notice=fixture()
    good=classify(contract,notice)
    assert good is not None and good['status']=='procedure_ids',good
    assert good['notice_sha256'] and len(good['notice_sha256'])==64
    assert exact_token('AO25DMISP','ref AO25DMISP, lot n°2')
    assert not exact_token('AO25DMISP','AO25DMISPXX')
    candidate=dict(notice,DONNEES={'procedure':{'reference':'AO25DMISP'}})
    mid=classify(contract,candidate)
    assert mid is not None and mid['status']=='candidate',mid
    rejected=dict(notice,DONNEES={'acheteur':{'identifiant':'11111111111111'}})
    assert classify(contract,rejected) is None
    other_buyer=dict(contract,buyer_id='11111111111111')
    assert classify(other_buyer,notice) is None
    invalid_url=dict(notice,url_avis='https://example.org/phishing')
    assert classify(contract,invalid_url) is None
    invalid_id=dict(notice,idweb='xyz')
    assert classify(contract,invalid_id) is None
    # Procedure-level notice cannot establish the legal identity of a lot.
    # A title match without buyer/reference identifiers is not confirmed.
    title_only=dict(notice,DONNEES={'objet':notice['objet']})
    assert classify(contract,title_only) is None
    with sqlite3.connect(':memory:') as sidecar:
        sidecar.executescript(SCHEMA)
        store(sidecar,contract['contract_key'],[good])
        store(sidecar,contract['contract_key'],[good])
        assert sidecar.execute('SELECT COUNT(*) FROM reference_matches').fetchone()[0]==1
        assert sidecar.execute('SELECT COUNT(*) FROM checks').fetchone()[0]==1
        store(sidecar,contract['contract_key'],[],status='failed',message='Erreur réseau')
        assert sidecar.execute('SELECT status FROM checks').fetchone()[0]=='failed'
        # Do not erase a historical notice on network error.
        assert sidecar.execute('SELECT COUNT(*) FROM reference_matches').fetchone()[0]==1
    print('PASS BOAMP: exact 2-ID procedure check, no lot/payment inference, safe official links, idempotent sidecar.')


if __name__ == '__main__':
    main()
