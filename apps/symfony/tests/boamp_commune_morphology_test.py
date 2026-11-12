#!/usr/bin/env python3
"""Offline regression for the full INSEE commune morphology audit."""
from __future__ import annotations
import csv
from pathlib import Path
import sqlite3
import sys
from tempfile import TemporaryDirectory

sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
from audit_boamp_commune_morphology import clean,inspect,write_results
from reconcile_procurement_boamp import title_terms


def main():
    assert clean("L'Haÿ-les-Roses")=="l hay les roses"
    assert 'internat' in title_terms("chambres d'internats")
    assert 'internat' in title_terms("chambre d'internat")
    assert 'normandie' not in title_terms('normands')
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
            """)
        rows,summary=inspect(path,minimum=6)
        assert summary['communes']==6
        assert summary['codes_uniques']==6
        assert summary['cles_lexicales_ambigues']>=4
        index={r['code_insee']:r for r in rows}
        assert index['01001']['homonymes_cle_lexicale']>=2
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
        assert len(listed)==6
        assert {r['code_insee'] for r in listed}=={r['code_insee'] for r in rows}
        try:inspect(path,minimum=7)
        except ValueError:pass
        else:raise AssertionError('Un référentiel incomplet doit échouer')
        with sqlite3.connect(path) as db:
            db.execute("UPDATE metadata SET value='2025' WHERE key='vintage'")
        try:inspect(path,minimum=6)
        except ValueError:pass
        else:raise AssertionError('Millésime incorrect accepté')
    print('PASS COG 2026: contrôle exhaustif, noms officiels, homonymies, pluriels, base intacte.')


if __name__=='__main__':
    main()
