#!/usr/bin/env python3
"""Offline regression: DECP/BOAMP indexes remain independent; no paid totals inferred."""
from __future__ import annotations
import io
import json
from pathlib import Path
import sqlite3
import sys
from tempfile import TemporaryDirectory
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
from import_boamp_national import SCHEMA as BOAMP_SCHEMA,SCHEMA_VERSION,import_jsonl,EXPORT
from match_boamp_national import (LOCAL_SCHEMA,process,source_ready,evaluate,postings,save,complete_official_export,short_unique_communes)

def sample_notice(ref='24VRA0101',obj='Fourniture De Panneaux De Signalisation Verticale Et Accessoires'):
    return {'idweb':'24-30283','objet':obj,'nomacheteur':'Commune de test',
        'dateparution':'2024-03-13','url_avis':'https://www.boamp.fr/pages/avis/?q=idweb:24-30283',
        'donnees':{'SIRET':'21690264300016','ID':ref,'source':'Test officiel simulé'}}

def test_normandie_local_with_invalid_decp_year():
    """Realistic indexed BOAMP notices and a malformed 2926 DECP year."""
    contract={'contract_key':'n'*40,'buyer_id':'20005340300057',
        'original_id':'20260211','notice_date':'2926-10-12',
        'object':("Acquisition de mobiliers d'hébergement pour les chambres "
                  "d'internats des établissements publics d'enseignement normands")}
    title=("Acquisition de mobiliers d'hébergement pour les chambres "
           "d'internat à destination des lycées de Normandie (EPLE)")
    with sqlite3.connect(':memory:') as boamp:
        boamp.row_factory=sqlite3.Row
        boamp.executescript(BOAMP_SCHEMA)
        boamp.execute("INSERT INTO metadata VALUES('schema',?)",(SCHEMA_VERSION,))
        for idweb in ('24-31681','24-80963'):
            notice={'idweb':idweb,'objet':title,'dateparution':'2024-05-15',
                'url_avis':'https://www.boamp.fr/pages/avis/?q=idweb:'+idweb,
                'donnees':{'SIRET':'20005340300057',
                           'reference':'different-public-procedure-number'}}
            import_jsonl(boamp,io.BytesIO((json.dumps(notice)+'\n').encode()),
                        'https://official.example/historical')
        candidate_notices,overflow=postings(boamp,contract['buyer_id'])
        assert not overflow and len(candidate_notices)==2
        linked=evaluate(contract,candidate_notices,overflow,boamp)
        assert sorted(x['idweb'] for x in linked)==['24-31681','24-80963']
        assert all(x['status']=='candidate' for x in linked)


def test_short_place_from_cog_not_common_name():
    with TemporaryDirectory() as tmp:
        path=Path(tmp)/'cog.sqlite'
        with sqlite3.connect(path) as geo:
            geo.executescript("""
                CREATE TABLE metadata(key TEXT,value TEXT);
                INSERT INTO metadata VALUES('vintage','2026');
                CREATE TABLE communes(code TEXT,name TEXT);
                INSERT INTO communes VALUES('05061','Gap');
                INSERT INTO communes VALUES('03208','Le Pin');
                INSERT INTO communes VALUES('14504','Le Pin');
                INSERT INTO communes VALUES('01057','Boz');
                INSERT INTO communes VALUES('01001','L Abergement Clémenciat');
            """)
        short=short_unique_communes(path)
        assert short=={'05061':'Gap','01057':'Boz'}
        with sqlite3.connect(':memory:') as boamp:
            boamp.row_factory=sqlite3.Row
            boamp.executescript(BOAMP_SCHEMA)
            boamp.execute("INSERT INTO metadata VALUES('schema',?)",(SCHEMA_VERSION,))
            n={'idweb':'25-76543','objet':'Réfection toiture médiathèque dans la commune de Gap',
               'dateparution':'2025-07-03',
               'url_avis':'https://www.boamp.fr/pages/avis/?q=idweb:25-76543',
               'donnees':{'SIRET':'20005340300057'}}
            import_jsonl(boamp,io.BytesIO((json.dumps(n)+'\n').encode()),
                         'https://official.example/cog-test')
            candidates,over=postings(boamp,'20005340300057')
            contract={'contract_key':'f'*40,'buyer_id':'20005340300057',
                      'original_id':'20261234',
                      'object':'Réfection toiture bibliothèque à Gap',
                      'notice_date':'2025-06-10',
                      'location_type':'Code commune','location_code':'05061'}
            assert evaluate(contract,candidates,over,boamp)==[]
            matches=evaluate(contract,candidates,over,boamp,short)
            assert len(matches)==1 and matches[0]['status']=='candidate'
            assert 'code INSEE 05061' in matches[0]['basis']
        with sqlite3.connect(path) as geo:
            geo.execute("UPDATE metadata SET value='2025' WHERE key='vintage'")
        try:short_unique_communes(path)
        except ValueError:pass
        else:raise AssertionError('Wrong COG vintage must fail')


def test_append_only_decision_versioning():
    """Reclassification, no-longer-observed, replay and legacy are traceable."""
    contract={
        'contract_key':'f'*40,'buyer_id':'21690264300016',
        'original_id':'24VRA0101',
        'object':'Fourniture de panneaux de signalisation verticale et accessoires',
        'notice_date':'2089-03-13','cpv':'34992200',
        'kind':'market','location_type':None,'location_code':None}
    with sqlite3.connect(':memory:') as side:
        side.row_factory=sqlite3.Row
        side.executescript(LOCAL_SCHEMA)
        original={
            'idweb':'24-30283','notice_sha':'a'*64,
            'status':'procedure_ids','basis':'SIRET et référence cités',
            'title':'Signalisation','url':'https://www.boamp.fr/pages/avis/?q=idweb:24-30283',
            'publication_date':'2024-03-13','notice_kind':'Avis',
            'rule_evidence':{'buyer_siret_cited':True,'title_jaccard':0.9}}
        save(side,contract['contract_key'],[original],15,False,'17',
             source_complete=False,contract=contract,rule_hash='1'*64)
        first=side.execute('SELECT * FROM local_decision_history').fetchall()
        assert len(first)==1
        assert first[0]['event_type']=='observed'
        assert first[0]['classification']=='procedure_ids'
        assert first[0]['algorithm_sha256']=='1'*64
        assert json.loads(first[0]['factors_json'])['title_jaccard']==0.9
        assert first[0]['corpus_status']=='partial'
        # Replaying identical rules + inputs + source does not inflate history.
        save(side,contract['contract_key'],[original],15,False,'17',
             source_complete=False,contract=contract,rule_hash='1'*64)
        assert side.execute('SELECT COUNT(*) FROM local_decision_history').fetchone()[0]==1
        changed=dict(original,status='candidate',basis='Même avis, titre seulement',
                     rule_evidence={'buyer_siret_cited':True,'title_jaccard':0.4})
        save(side,contract['contract_key'],[changed],15,False,'17',
             source_complete=False,contract=contract,rule_hash='2'*64)
        rows=side.execute("""SELECT classification,algorithm_sha256 FROM
            local_decision_history ORDER BY event_id""").fetchall()
        assert [r['classification'] for r in rows]==['procedure_ids','candidate']
        assert rows[-1]['algorithm_sha256']=='2'*64
        # No longer selected in a PARTIAL archive is not a proof of absence.
        save(side,contract['contract_key'],[],15,False,'18',
             source_complete=False,contract=contract,rule_hash='2'*64)
        last=side.execute("""SELECT * FROM local_decision_history
            ORDER BY event_id DESC LIMIT 1""").fetchone()
        assert last['event_type']=='not_observed'
        assert 'lien non exclu' in last['basis']
        assert side.execute("SELECT active FROM local_matches").fetchone()[0]==0
        save(side,contract['contract_key'],[],15,False,'18',
             source_complete=False,contract=contract,rule_hash='2'*64)
        assert side.execute('SELECT COUNT(*) FROM local_decision_history').fetchone()[0]==3
        save(side,contract['contract_key'],[changed],15,False,'18',
             source_complete=False,contract=contract,rule_hash='2'*64)
        assert side.execute('SELECT COUNT(*) FROM local_decision_history').fetchone()[0]==4
        # An earlier sidecar must not be retroactively attributed to v1.
        with sqlite3.connect(':memory:') as legacy:
            legacy.row_factory=sqlite3.Row
            legacy.executescript(LOCAL_SCHEMA)
            legacy.execute("""INSERT INTO local_matches VALUES
              (?,?,?,?,?,?,?,?,?,?,?,?)""",
              (contract['contract_key'],original['idweb'],original['notice_sha'],
               original['status'],original['basis'],original['title'],original['url'],
               original['publication_date'],original['notice_kind'],
               '2026-01-01','2026-01-01',1))
            save(legacy,contract['contract_key'],[original],15,False,'18',
                 source_complete=False,contract=contract,rule_hash='2'*64)
            captured=legacy.execute("""SELECT event_type,algorithm_version FROM
                 local_decision_history ORDER BY event_id""").fetchall()
            assert [(r['event_type'],r['algorithm_version']) for r in captured]==[
                ('legacy_snapshot','legacy-unversioned'),
                ('observed','boamp-local-v1.1-decision-history')]


def test_scan_history_including_zero_candidates():
    """Every contract has a trace, including no-match/partial checks."""
    contract={'contract_key':'e'*40,'buyer_id':'21690264300016',
        'original_id':'202412345678',
        'object':'Fourniture de matériel pour mairie',
        'notice_date':'6666-01-01',
        'cpv':'45200000','kind':'market',
        'location_type':None,'location_code':None}
    with sqlite3.connect(':memory:') as side:
        side.row_factory=sqlite3.Row
        side.executescript(LOCAL_SCHEMA)
        save(side,contract['contract_key'],[],0,False,'17',
             source_complete=False,contract=contract,rule_hash='a'*64)
        result=side.execute('SELECT * FROM local_scan_history').fetchone()
        assert result['result']=='indetermine'
        assert result['scanned_notices']==0 and result['matched_notices']==0
        assert result['corpus_status']=='partial'
        assert result['algorithm_sha256']=='a'*64
        assert side.execute('SELECT COUNT(*) FROM local_decision_history').fetchone()[0]==0
        # Repeated identical scans produce no duplicate history.
        save(side,contract['contract_key'],[],0,False,'17',
             source_complete=False,contract=contract,rule_hash='a'*64)
        assert side.execute('SELECT COUNT(*) FROM local_scan_history').fetchone()[0]==1
        # A change in inspected source coverage must become a new snapshot.
        save(side,contract['contract_key'],[],12,False,'18',
             source_complete=False,contract=contract,rule_hash='a'*64)
        assert side.execute('SELECT COUNT(*) FROM local_scan_history').fetchone()[0]==2
        # Even a complete scan with zero retained candidates is NOT
        # proof the contract has no documentary relation in other sources.
        save(side,contract['contract_key'],[],12,False,'18',
             source_complete=True,contract=contract,rule_hash='a'*64)
        last=side.execute('SELECT * FROM local_scan_history ORDER BY scan_id DESC LIMIT 1').fetchone()
        assert last['result']=='indetermine'
        assert last['corpus_status']=='checked'
        assert side.execute('SELECT COUNT(*) FROM local_scan_history').fetchone()[0]==3
        new_contract=dict(contract,object='Fourniture de matériel pour une école')
        save(side,contract['contract_key'],[],12,False,'18',
             source_complete=True,contract=new_contract,rule_hash='a'*64)
        assert side.execute('SELECT COUNT(*) FROM local_scan_history').fetchone()[0]==4
        save(side,contract['contract_key'],[],12,False,'18',
             source_complete=True,contract=new_contract,rule_hash='b'*64)
        assert side.execute('SELECT COUNT(*) FROM local_scan_history').fetchone()[0]==5
        # A partial capped scan has to retain the truncation marker.
        save(side,contract['contract_key'],[],4,True,'18',
             source_complete=True,contract=new_contract,rule_hash='b'*64)
        last=side.execute('SELECT * FROM local_scan_history ORDER BY scan_id DESC LIMIT 1').fetchone()
        assert last['truncated']==1 and last['corpus_status']=='partial'


def main():
    test_scan_history_including_zero_candidates()
    test_append_only_decision_versioning()
    test_short_place_from_cog_not_common_name()
    test_normandie_local_with_invalid_decp_year()
    with sqlite3.connect(':memory:') as boamp, sqlite3.connect(':memory:') as decp, sqlite3.connect(':memory:') as side:
        for db in (boamp,decp,side):db.row_factory=sqlite3.Row
        boamp.executescript(BOAMP_SCHEMA)
        boamp.execute("INSERT INTO metadata VALUES('schema',?)",(SCHEMA_VERSION,))
        decp.executescript("""CREATE TABLE contracts(
            contract_key TEXT PRIMARY KEY,buyer_id TEXT,original_id TEXT,
            object TEXT,notice_date TEXT,cpv TEXT,kind TEXT);""")
        decp.executemany('INSERT INTO contracts VALUES(?,?,?,?,?,?,?)',[
            ('a'*40,'21690264300016','24VRA0101',
             'Fourniture de panneaux de signalisation verticale et accessoires','2089-03-13','34992200','market'),
            ('b'*40,'21690264300016','24VRA0102',
             'Fourniture de panneaux de signalisation verticale et accessoires','2024-03-13','34992200','market'),
            ('c'*40,'11111111111111','24VRA0101',
             'Fourniture de panneaux de signalisation verticale et accessoires','2024-03-13','34992200','market')])
        row=sample_notice()
        contents=(json.dumps(row)+'\n').encode()
        count,updated,_=import_jsonl(boamp,io.BytesIO(contents),'https://official.example/export')
        assert (count,updated)==(1,1)
        source_ready(boamp)
        assert not complete_official_export(boamp), 'Un import partiel ne couvre pas le BOAMP national'
        side.executescript(LOCAL_SCHEMA)
        records=[dict(r) for r in decp.execute('SELECT * FROM contracts')]
        report=process(decp,boamp,side,records)
        assert report['contracts']==3
        assert report['matches']==2
        found=side.execute('SELECT contract_key,status,active FROM local_matches ORDER BY contract_key').fetchall()
        assert len(found)==2
        assert found[0]['status']=='procedure_ids'
        assert found[1]['status']=='candidate'
        # Future/garbled date does not suppress a documentary link.
        assert side.execute("SELECT status FROM local_checks WHERE contract_key=?",('a'*40,)).fetchone()[0]=='partial'
        # A documented, successful full official export is the only source
        # eligible to make a non-truncated local scan 'checked'.
        import_jsonl(boamp,io.BytesIO(contents),EXPORT)
        assert complete_official_export(boamp)
        process(decp,boamp,side,records)
        assert side.execute("SELECT status FROM local_checks WHERE contract_key=?",('a'*40,)).fetchone()[0]=='checked'
        assert side.execute("SELECT matched_notices FROM local_checks WHERE contract_key=?",('c'*40,)).fetchone()[0]==0
        process(decp,boamp,side,records)
        assert side.execute('SELECT COUNT(*) FROM local_matches').fetchone()[0]==2
        assert boamp.execute('SELECT COUNT(*) FROM notice_versions').fetchone()[0]==1
        assert decp.execute('SELECT COUNT(*) FROM contracts').fetchone()[0]==3
        # A newer BOAMP version can revise the relation; keep old versions.
        row2=sample_notice(ref='DIFFERENT')
        import_jsonl(boamp,io.BytesIO((json.dumps(row2)+'\n').encode()),'https://official.example/export')
        process(decp,boamp,side,records)
        assert side.execute('SELECT COUNT(*) FROM local_matches WHERE contract_key=?',('a'*40,)).fetchone()[0]>=2
        assert boamp.execute('SELECT COUNT(*) FROM notice_versions').fetchone()[0]==2
        # A buyer with more notices than the local cap is never labelled
        # 'no link'; the audit explicitly records partial coverage.
        second=dict(row,idweb='24-1573',
                    url_avis='https://www.boamp.fr/pages/avis/?q=idweb:24-1573')
        import_jsonl(boamp,io.BytesIO((json.dumps(second)+'\n').encode()),'https://official.example/export')
        entries_all,no_cap=postings(boamp,'21690264300016',0)
        assert len(entries_all)==2 and not no_cap
        entries,overflow=postings(boamp,'21690264300016',1)
        assert len(entries)==1 and overflow
        limited=process(decp,boamp,side,records,max_candidates=1)
        assert limited['partial']==2
        assert side.execute("SELECT status FROM local_checks WHERE contract_key=?",('a'*40,)).fetchone()[0]=='partial'
    print('PASS offline BOAMP: DECP read-only, strict source versions, local classification, anomalies, preserved historical matches.')

if __name__=='__main__':
    main()
