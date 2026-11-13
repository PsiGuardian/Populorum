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
from reconcile_procurement_boamp import classify, SCHEMA, store, exact_token, unique_notice_total, following_offset, fetch_candidates, search_expression, title_terms, terms, geographic_text, corroborated_short_commune, project_object_conflict, distinct_procurement_phase, boamp_heading_lots, decp_declared_lot_number, lot_title_evidence, eforms_award_lot_result_evidence
from audit_procurement_concordance import contracts as audit_contracts, run as audit_report, official_query as audit_official_query, decision_timeline, scan_timeline


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
        without_local=audit_report(national,sidecar,selected,False,True)
        assert 'NON INSTALLE' in without_local
        sidecar.executescript("""CREATE TABLE local_checks (
            contract_key TEXT PRIMARY KEY,status TEXT,scanned_notices INTEGER,
            matched_notices INTEGER,truncated INTEGER,source_revision TEXT);
        CREATE TABLE local_matches (
            contract_key TEXT,idweb TEXT,status TEXT,basis TEXT,title TEXT,
            url TEXT,publication_date TEXT,notice_kind TEXT,active INTEGER);
        INSERT INTO local_checks VALUES (
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','partial',22,1,0,'2');
        INSERT INTO local_matches VALUES (
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','26-123456','candidate',
            'SIRET et titre seulement','Dispositifs médicaux',
            'https://www.boamp.fr/pages/avis/?q=idweb:26-123456',
            '2026-04-01','Avis',1);
        """)
        with_local=audit_report(national,sidecar,selected,False,True)
        assert '22' in with_local and 'couverture partielle' in with_local
        assert 'CANDIDATE BOAMP 26-123456' in with_local
        assert 'LOCAL : 1 contrats examinés' in with_local
        assert national.execute('SELECT COUNT(*) FROM contracts').fetchone()[0]==2
        assert decision_timeline(sidecar,good_contract['contract_key'])==[]
        assert scan_timeline(sidecar,good_contract['contract_key'])==[]
        with_history=audit_report(national,sidecar,selected,False,True,True)
        assert 'Aucun événement versionné disponible' in with_history


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


def test_normandie_title_plural():
    decp_title=("Acquisition de mobiliers d'hébergement pour les chambres "
        "d'internats des établissements publics d'enseignement normands")
    boamp_title=("Acquisition de mobiliers d'hébergement pour les chambres "
        "d'internat à destination des lycées de Normandie (EPLE)")
    raw_a=set(terms(decp_title))
    raw_b=set(terms(boamp_title))
    assert len(raw_a & raw_b)==4
    assert len(raw_a & raw_b)/len(raw_a | raw_b)<0.35
    a=title_terms(decp_title)
    b=title_terms(boamp_title)
    assert 'internat' in a and 'internat' in b
    assert len(a & b)>=5
    assert len(a & b)/len(a | b)>=0.35

    contract={'contract_key':'a'*40,'buyer_id':'20005340300057',
              'original_id':'20260211','object':decp_title,
              'notice_date':'2926-10-12'}
    for idweb in ('24-31681','24-80963'):
        notice={'idweb':idweb,'objet':boamp_title,'dateparution':'2024-05-15',
            'url_avis':'https://www.boamp.fr/pages/avis/?q=idweb:'+idweb,
            'donnees':{'SIRET':'20005340300057',
                       'reference':'different-public-procedure-number'}}
        result=classify(contract,notice)
        assert result is not None and result['status']=='candidate',idweb
        # Invalid DECP year 2926 is preserved but must not eliminate
        # a well-documented candidate. A plausible but distant year can.
        plausible_old=dict(contract,notice_date='2018-10-12')
        assert classify(plausible_old,notice) is None
        # No invented contract-level proof: the original DECP reference
        # does not appear in the BOAMP notice.
        assert result['notice_total_eur']==''
        other_buyer=dict(notice,donnees={'SIRET':'11111111111111'})
        assert classify(contract,other_buyer) is None
        other_subject=dict(notice,objet='Acquisition de logiciels de comptabilité')
        assert classify(contract,other_subject) is None


def test_short_communes_need_explicit_context_and_decp_code():
    # The real COG 2026 contains Gap (unique code 05061), but 9 distinct
    # municipalities named Le Pin; only unique names may be augmented.
    contract={
        'contract_key':'f'*40,
        'buyer_id':'20005340300057',
        'original_id':'20261234',
        'object':'Réfection toiture bibliothèque à Gap',
        'notice_date':'2025-06-10',
        'location_type':'Code commune',
        'location_code':'05061',
    }
    notice={
        'idweb':'25-76543',
        'objet':'Réfection toiture médiathèque dans la commune de Gap',
        'url_avis':'https://www.boamp.fr/pages/avis/?q=idweb:25-76543',
        'dateparution':'2025-07-03',
        'donnees':{'acheteur':{'siret':'20005340300057'},
                   'reference':'DIFFERENT-NUMBER'}
    }
    geo={'05061':'Gap'}
    assert geographic_text("Cap-d'Ail")=='cap d ail'
    assert corroborated_short_commune(contract,notice['objet'],geo)=='05061'
    assert classify(contract,notice) is None, 'No silent change without COG'
    match=classify(contract,notice,short_communes=geo)
    assert match is not None and match['status']=='candidate',match
    assert 'code INSEE 05061' in match['basis']
    assert match['notice_total_eur']==''
    assert classify({**contract,'location_code':'05062'},notice,geo) is None
    assert classify({**contract,'location_type':'Code postal'},notice,geo) is None
    assert classify(contract,{**notice,'objet':'Réfection toiture médiathèque GAP'},geo) is None
    assert classify(contract,{**notice,'objet':'Fabrication de dispositifs pour Gap'},geo) is None
    assert classify(contract,{
        **notice,'donnees':{'acheteur':{'siret':'11111111111111'}}
    },geo) is None

    ambiguous={**contract,'location_code':'03208',
               'object':'Réfection toiture bibliothèque au Le Pin'}
    another={**notice,'objet':'Réfection toiture médiathèque dans la commune de Le Pin'}
    assert classify(ambiguous,another,geo) is None, (
        'Ambiguous Le Pin must never acquire an INSEE code from text')


def test_tierce_project_conflict_is_narrow():
    same=("Concours de maitrise d'œuvre restreint pour la création "
          "d'un pôle enfance à Tiercé - Phase offre")
    right=("Concours de maitrise d'œuvre restreint pour la création "
           "d'un pôle enfance à Tiercé.")
    wrong=("Concours de maitrise d'œuvre restreint pour la création "
           "d'une maison de l'économie à Tiercé.")
    flag=project_object_conflict(same,wrong)
    assert flag is not None and flag['reason_code']=='creation_targets_disjoint'
    assert flag['decp_target']=='pole enfance'
    assert flag['boamp_target']=='maison de l economie'
    assert project_object_conflict(same,right) is None
    # Shared distinctive project object prevents unjustified rejection.
    assert project_object_conflict(
        same,"Concours pour la création d'un centre enfance à Tiercé") is None
    # Missing creation phrase or single-word targets are not actionable.
    assert project_object_conflict(
        same,"Fourniture de panneaux de signalisation à Tiercé") is None
    assert project_object_conflict(
        same,"Concours pour la création d'une école à Tiercé") is None
    # Multicomponent operations are too ambiguous for this heuristic.
    assert project_object_conflict(
        same,"Concours pour la création d'une maison de l'économie "
             "et d'un pôle enfance à Tiercé") is None
    # A title in a different construction phase may refer to the same project.
    assert project_object_conflict(
        same,"Travaux de construction d'un pôle enfance à Tiercé") is None


def test_chessy_works_vs_design_services():
    works={'object':("Pièce d'eau et aménagements écologiques - "
                     "Aménagement paysager du parc du château de Chessy"),
           'cpv':'45232454-1','kind':'market'}
    previous=("Maitrise d'oeuvre pour des travaux d'aménagement paysagers "
              "au parc du château de Chessy")
    flag=distinct_procurement_phase(works,previous)
    assert flag is not None
    assert flag['reason_code']=='decp_works_vs_boamp_design_services'
    assert flag['decp_cpv']=='45232454-1'
    assert flag['legal_link_excluded'] is False
    assert flag['lot_identity_established'] is False
    # Not a different phase just because the notice has several lots
    # or is about the whole works procedure.
    assert distinct_procurement_phase(
        works,"Aménagement paysager du parc du château de Chessy "
              "Lot n°3 : Pièce d'eau et aménagements écologiques") is None
    # Ambiguous/mixed titles must stay candidates for deeper checks.
    assert distinct_procurement_phase(
        works,"Travaux et maîtrise d'œuvre pour le parc de Chessy") is None
    assert distinct_procurement_phase(
        works,"Marché global de conception-réalisation "
              "avec maîtrise d'œuvre au parc de Chessy") is None
    assert distinct_procurement_phase(
        {**works,'cpv':'71000000-8'}, previous) is None
    assert distinct_procurement_phase(
        {**works,'object':"Maîtrise d'œuvre et travaux au parc de Chessy"},
        previous) is None
    assert distinct_procurement_phase(
        {**works,'cpv':'4523245'},previous) is None


def test_explicit_lot_heading_does_not_certify_award():
    chessy={
        'original_id':'2025300000000000',  # Not a lot identifier!
        'object':("Pièce d'eau et aménagements écologiques - "
                  "Aménagement paysager du parc du château de Chessy"),
        'cpv':'45232454-1'}
    notice=("Aménagement paysager du parc du château de Chessy "
            "Lot n°1 : Aménagement paysagers et plantations "
            "Lot n°2 : VRD "
            "Lot n°3 : Pièce d'eau et aménagements écologiques")
    lot=lot_title_evidence(chessy,notice)
    assert lot is not None
    assert [x['number'] for x in lot['advertised_lots']]==['1','2','3']
    assert lot['method']=='unique_lot_title_similarity'
    assert lot['possible_boamp_lot_number']=='3'
    assert lot['title_jaccard']==1.0
    assert lot['decp_explicit_lot_number'] is None
    assert not lot['legal_lot_identity_confirmed']
    assert not lot['award_or_payment_confirmed']
    assert not lot['advertised_lots_exhaustive']
    assert decp_declared_lot_number(chessy) is None
    # The entire project name alone does not imply an awarded lot.
    ambiguous=dict(chessy,object='Aménagement paysager du parc du château de Chessy')
    unresolved=lot_title_evidence(ambiguous,notice)
    assert unresolved is not None
    assert unresolved['method']=='indeterminate_title_only'
    assert 'possible_boamp_lot_number' not in unresolved
    # Both documents mentioning lot 3 is a stated number, not a legal link.
    explicit=dict(chessy,object="Lot 03 - Pièce d'eau et aménagements écologiques")
    same=lot_title_evidence(explicit,notice)
    assert same['method']=='explicit_lot_number_correspondence'
    assert same['decp_explicit_lot_number']=='3'
    assert same['possible_boamp_lot_number']=='3'
    # If the lot number isn't enumerated, don't infer a legal exclusion.
    other=dict(chessy,object='LOT 12 PLOMBERIE')
    absent=lot_title_evidence(other,notice)
    assert absent['method']=='decp_lot_number_absent_from_published_heading'
    assert 'possible_boamp_lot_number' not in absent
    assert not absent['advertised_lots_exhaustive']
    # Malformed or contradictory BOAMP headings are not reliable lot lists.
    assert boamp_heading_lots("Lot n°3 : Eau Lot n°3 : Eau") == []
    assert boamp_heading_lots("Lot n°3 :  Lot n°4 : VRD") == []
    assert boamp_heading_lots("Aucun lot décrit") == []
    assert lot_title_evidence(chessy,"Aménagement paysager du parc de Chessy") is None
    conflict=dict(chessy,object="LOT 2 et LOT 3 : même marché")
    assert decp_declared_lot_number(conflict) is None


def test_official_eforms_result_links_have_notice_local_scope():
    example={'idweb':'26-109517','donnees':{'EFORMS':{'ContractAwardNotice':{
        'ext:UBLExtensions':{'ext:UBLExtension':{'ext:ExtensionContent':{
            'efext:EformsExtension':{'efac:NoticeResult':{
                'efac:LotResult':[
                    {'cbc:ID':{'@schemeName':'result','#text':'RES-0001'},
                     'cbc:TenderResultCode':{'@listName':'winner-selection-status',
                                              '#text':'selec-w'},
                     'efac:TenderLot':{'cbc:ID':'LOT-0003'},
                     'efac:LotTender':{'cbc:ID':'TEN-0001'},
                     'efac:SettledContract':{'cbc:ID':'CON-0001'}},
                    {'cbc:ID':'RES-0002',
                     'cbc:TenderResultCode':{'#text':'clos-nw'},
                     'efac:TenderLot':{'cbc:ID':'LOT-0004'}}
                ]}}}}}}}}}
    proof=eforms_award_lot_result_evidence(example)
    assert proof is not None
    assert proof['notice_idweb']=='26-109517'
    assert proof['method']=='dila_eforms_contract_award_notice_result'
    assert len(proof['links'])==2
    assert proof['links'][0]=={
        'eforms_result_id':'RES-0001','eforms_lot_id':'LOT-0003',
        'eforms_tender_id':'TEN-0001',
        'eforms_settled_contract_id':'CON-0001',
        'winner_selection_code':'selec-w'}
    assert proof['links'][1]['eforms_settled_contract_id'] is None
    assert proof['links'][1]['winner_selection_code']=='clos-nw'
    assert proof['identifiers_unique_within_notice_only']
    assert not proof['decp_contract_identity_established']
    assert not proof['lot_award_established_from_this_join']
    assert not proof['invoice_or_payment_established']
    # Same internal LOT-0003 in other notices cannot be joined by itself.
    another={**example,'idweb':'26-109518'}
    found=eforms_award_lot_result_evidence(another)
    assert found['notice_idweb']!='26-109517'
    assert found['links'][0]['eforms_lot_id']=='LOT-0003'
    # Unstructured text, wrong XML paths and other notice types do not pass.
    assert eforms_award_lot_result_evidence({
        'idweb':'26-109517','donnees':{'EFORMS':{'ContractNotice':{
            'ext:UBLExtensions':example['donnees']['EFORMS']['ContractAwardNotice']['ext:UBLExtensions']}}}}) is None
    assert eforms_award_lot_result_evidence({
        'idweb':'26-109517','donnees':{'EFORMS':{'ContractAwardNotice':{
            'efac:NoticeResult':{'efac:LotResult':{'cbc:ID':'RES-0001',
                'efac:TenderLot':{'cbc:ID':'LOT-0003'}}}}}}}) is None
    broken=json.loads(json.dumps(example))
    first=broken['donnees']['EFORMS']['ContractAwardNotice']['ext:UBLExtensions'][
        'ext:UBLExtension']['ext:ExtensionContent']['efext:EformsExtension'][
        'efac:NoticeResult']['efac:LotResult'][0]
    first['efac:TenderLot']={'cbc:ID':'LOT-0003-SUFFIX'}
    result=eforms_award_lot_result_evidence(broken)
    assert len(result['links'])==1 and result['links'][0]['eforms_lot_id']=='LOT-0004'


def main():
    test_official_eforms_result_links_have_notice_local_scope()
    test_explicit_lot_heading_does_not_certify_award()
    test_chessy_works_vs_design_services()
    test_short_communes_need_explicit_context_and_decp_code()
    test_normandie_title_plural()
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
