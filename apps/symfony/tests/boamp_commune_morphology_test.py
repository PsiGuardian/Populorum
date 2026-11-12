#!/usr/bin/env python3
"""Offline regression for the full INSEE commune morphology audit."""
from __future__ import annotations
import csv
from pathlib import Path
import sqlite3
import sys
from tempfile import TemporaryDirectory

sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
from audit_boamp_commune_morphology import clean,inspect,write_results,geographic_tokens,keys
from reconcile_procurement_boamp import title_terms


def main():
    assert clean("L'Haÿ-les-Roses")=="l hay les roses"
    assert 'internat' in title_terms("chambres d'internats")
    assert 'internat' in title_terms("chambre d'internat")
    assert 'normandie' not in title_terms('normands')
    assert geographic_tokens('Bey')==('bey',)
    assert geographic_tokens('Le Pin')==('le','pin')
    assert keys('Boz')[0]!=keys('Bey')[0]
    assert keys('Gap')[0]!=keys('Le Pin')[0]
    assert keys('Les Essarts')[0]!=keys('Les Essart')[0]
    assert keys('Les Essarts')[1]==keys('Les Essart')[1]
    with TemporaryDirectory() as tmp:
        root=Path(tmp)
        path=root/'communes.sqlite'
        with sqlite3.connect(path) as db:
            db.executescript("""
                CREATE TABLE metadata (key TEXT PRIMARY KEY,value TEXT);
                INSERT INTO metadata VALUES ('vintage','2026');
                CREATE TABLE communes (code TEXT PRIMARY KEY,name TEXT);
                INSERT INTO communes VALUES ('01001','Les Essarts');
                INSERT INTO communes VALUES ('01002','Les Essart');
                INSERT INTO communes VALUES ('01003','Paris');
                INSERT INTO communes VALUES ('01004','Thiers');
                INSERT INTO communes VALUES ('01005','Saint-Florent');
                INSERT INTO communes VALUES ('01006','Saint Florent');
                INSERT INTO communes VALUES ('01007','Bey');
                INSERT INTO communes VALUES ('01008','Boz');
                INSERT INTO communes VALUES ('01009','Le Pin');
                INSERT INTO communes VALUES ('01010','Gap');
            """)
        rows,summary=inspect(path,minimum=10)
        assert summary['communes']==10
        assert summary['codes_uniques']==10
        assert summary['cles_vides']==0
        assert summary['communes_sans_mot_distinctif_dans_moteur_titres']>=4
        assert summary['cles_lexicales_ambigues']==2
        assert summary['communes_ambigues_supplementaires_apres_pluriels']==2
        assert summary['libelles_affectes_par_normalisation']>=2
        index={r['code_insee']:r for r in rows}
        assert index['01001']['homonymes_cle_lexicale']==1
        assert index['01001']['homonymes_cle_pluriel']==2
        assert index['01001']['ambiguite_introduite_par_pluriel']==1
        for code in ('01007','01008','01009','01010'):
            assert index[code]['homonymes_cle_lexicale']==1
            assert index[code]['cle_lexicale']
            assert index[code]['cle_pluriel']
            assert index[code]['cle_moteur_titres']==''
        assert index['01005']['homonymes_nom_officiel']==2
        assert index['01003']['termes_modifies']==''
        assert 'thiers' in index['01004']['termes_modifies']
        report=root/'audit.txt'
        table=root/'communes.tsv'
        write_results(rows,summary,report,table)
        content=report.read_text(encoding='utf-8')
        assert '6' in content and 'gentilés' in content
        with table.open(encoding='utf-8',newline='') as stream:
            listed=list(csv.DictReader(stream,delimiter='\t'))
        assert len(listed)==10
        assert {r['code_insee'] for r in listed}=={r['code_insee'] for r in rows}
        try:inspect(path,minimum=11)
        except ValueError:pass
        else:raise AssertionError('Un référentiel incomplet doit échouer')
        with sqlite3.connect(path) as db:
            db.execute("UPDATE metadata SET value='2025' WHERE key='vintage'")
        try:inspect(path,minimum=10)
        except ValueError:pass
        else:raise AssertionError('Millésime incorrect accepté')
    print('PASS COG 2026: noms courts, clés non vides, ambiguïtés strictes vs pluriels, base intacte.')


if __name__=='__main__':
    main()
