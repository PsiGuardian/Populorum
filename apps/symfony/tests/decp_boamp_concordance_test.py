#!/usr/bin/env python3
"""Unit tests for offline BOAMP procedural concordance, without network."""
from __future__ import annotations
from pathlib import Path
import json
import sqlite3
import sys
from unittest.mock import patch
from urllib.parse import parse_qs, urlsplit
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
from reconcile_procurement_boamp import classify, SCHEMA, store, exact_token, unique_notice_total, following_offset, fetch_candidates, search_expression
from audit_procurement_concordance import contracts as audit_contracts, run as audit_report, official_query as audit_official_query


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


def test_http_request_contract():
    # The live Opendatasoft BOAMP field is lowercase "donnees" (uppercase
    # "DONNEES" is a UI label and causes an ODSQL HTTP 400).
    class FakeResponse:
        url = 'https://boamp-datadila.opendatasoft.com/api/explore/v2.1/catalog/datasets/boamp/records'
        def __enter__(self):
            return self
        def __exit__(self, *args):
            return False
        def read(self, size):
            return json.dumps({'results':[{'idweb':'26-123456','donnees':'{}'}]}).encode()

    def fake_open(request, timeout):
        args = parse_qs(urlsplit(request.full_url).query)
        assert args['select'] == ['idweb,objet,nomacheteur,dateparution,url_avis,nature_categorise_libelle,donnees']
        assert args['where'] == ["search(donnees, '26380030200014') AND search(objet, 'implantables')"]
        assert timeout <= 30
        return FakeResponse()

    with patch('reconcile_procurement_boamp.urlopen', fake_open):
        result = fetch_candidates('Fourniture de dispositifs médicaux implantables et spécialisés', 'AO25DMISP', '26380030200014')
    assert len(result) == 1 and result[0]['idweb'] == '26-123456'
    try:
        search_expression('Produits', '202500007', '12345')
    except ValueError:
        pass
    else:
        raise AssertionError('SIRET invalide autorisé')



def test_audit_read_only():
    with sqlite3.connect(':memory:') as national, sqlite3.connect(':memory:') as sidecar:
        national.row_factory=sidecar.row_factory=sqlite3.Row
        national.executescript("""CREATE TABLE contracts(
          contract_key TEXT PRIMARY KEY, buyer_id TEXT, original_id TEXT,
          object TEXT,notice_date TEXT,cpv TEXT,amount_initial_cents INTEGER,kind TEXT);
        INSERT INTO contracts VALUES ('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','26380030200014',
          'AO25DMISP','Fourniture de dispositifs médicaux implantables',
          '2025-05-20','33140000',9999999999900,'market');
        INSERT INTO contracts VALUES ('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb','12345678901234',
          '20251111','Fourniture de matériels hospitaliers',
          '2025-01-02','33140000',10000,'market');""")
        sidecar.executescript(SCHEMA)
        selected=audit_contracts(national,None,25,0)
        assert len(selected)==2
        summary=audit_report(national,sidecar,selected,False)
        assert 'INDETERMINE' in summary
        assert '99 999 999 999,00 EUR' in summary
        assert '0 concordances procedures' in summary
        good_contract,notice=fixture()
        sidecar.execute("""INSERT INTO checks VALUES(?,?,?,?,?)""",
                        (good_contract['contract_key'],'2026-11-12','ok',1,''))
        good=classify(good_contract,notice)
        store(sidecar,good_contract['contract_key'],[good])
        result=audit_report(national,sidecar,selected,False)
        assert 'PROCEDURE_IDS BOAMP 26-123456' in result
        assert '1 concordances procedures' in result
        assert sidecar.execute('SELECT COUNT(*) FROM checks').fetchone()[0]==1
        assert national.execute('SELECT COUNT(*) FROM contracts').fetchone()[0]==2


def test_audit_official_query_params():
    class Response:
        url='https://boamp-datadila.opendatasoft.com/api/explore/v2.1/catalog/datasets/boamp/records'
        def __enter__(self):return self
        def __exit__(self,*args):return False
        def read(self,size):return json.dumps({'total_count':30,'results':[]}).encode()
    def fake_open(request,timeout):
        params=parse_qs(urlsplit(request.full_url).query)
        assert params['where'] == ["search(donnees, '26380030200014') AND search(objet, 'implantables')"]
        assert params['limit']==['25']
        return Response()
    with patch('audit_procurement_concordance.urlopen',fake_open):
        count,items=audit_official_query('26380030200014','objet','implantables')
        assert count==30 and items==[]


def main():
    assert following_offset(40,10,10)==50
    assert following_offset(50,7,10)==0
    test_http_request_contract()
    test_audit_read_only()
    test_audit_official_query_params()
    contract,notice=fixture()
    good=classify(contract,notice)
    assert good is not None and good['status']=='procedure_ids',good
    assert good['notice_sha256'] and len(good['notice_sha256'])==64
    assert unique_notice_total({'cbc:TotalAmount':{'@currencyID':'EUR','#text':'335600'}})=='335600.00'
    assert unique_notice_total([{'cbc:TotalAmount':{'@currencyID':'EUR','#text':'100'}},
                                {'cbc:TotalAmount':{'@currencyID':'EUR','#text':'200'}}])==''
    assert unique_notice_total({'cbc:TotalAmount':{'@currencyID':'USD','#text':'100'}})==''
    assert good['notice_total_eur']==''
    assert exact_token('AO25DMISP','ref AO25DMISP, lot n°2')
    assert not exact_token('AO25DMISP','AO25DMISPXX')
    candidate=dict(notice,DONNEES={'acheteur':{'identifiant':'26380030200014'}})
    mid=classify(contract,candidate)
    assert mid is not None and mid['status']=='candidate',mid
    rejected=dict(notice,DONNEES={'acheteur':{'identifiant':'11111111111111'},
                                   'procedure':{'reference':'AO25DMISP'}})
    purely_numeric=dict(contract,original_id='202500001234')
    assert classify(purely_numeric,notice) is not None
    short_numeric=dict(contract,original_id='123456')
    numeric_notice=dict(notice,DONNEES={'acheteur':{'identifiant':'26380030200014'},
                                        'rubrique':{'prix':'123456'}})
    number_guess=classify(short_numeric,numeric_notice)
    assert number_guess is not None and number_guess['status']=='candidate'


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
