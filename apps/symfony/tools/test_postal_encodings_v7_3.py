#!/usr/bin/env python3
"""Tests hors réseau et hors base de production pour l'import La Poste."""
from __future__ import annotations
import importlib.util
from pathlib import Path
import sqlite3
import tempfile

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('postal_importer', HERE/'import_codes_postaux.py')
mod=importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)


def make_db(path):
    with sqlite3.connect(path) as db:
        db.executescript('''CREATE TABLE communes(code TEXT PRIMARY KEY,name TEXT);
          CREATE TABLE metadata(key TEXT PRIMARY KEY,value TEXT);''')
        db.executemany('INSERT INTO communes VALUES(?,?)',
            [(f'{i:05d}',f'Commune {i}') for i in range(10000, 41000)] +
            [('66134', 'Passa'),('76351','Le Havre')])


def make_csv(encoding):
    rows=['Code_commune_INSEE;Nom_commune;Code_postal;Libelle_acheminement;Ligne_5',
          '66134;Passa;66300;PASSA;',
          '76351;Le Havre;76600;LE HAVRE;',
          '76351;Le Havre;76610;LE HAVRE;']
    rows += [f'{i:05d};Commune {i};{i:05d};SAINT-ÉLOI;' for i in range(10000, 41000)]
    return ('\r\n'.join(rows)+'\r\n').encode(encoding)


def main():
    with tempfile.TemporaryDirectory(prefix='populorum-postal-test-') as temp:
        root=Path(temp)
        db=root/'communes.sqlite'
        make_db(db)
        source=root/'source.csv'
        for enc in ('cp1252', 'utf-8-sig', 'iso-8859-1'):
            source.write_bytes(make_csv(enc))
            n=mod.run(source,db,'test-'+enc)
            assert n==31003, (enc,n)
            with sqlite3.connect(db) as con:
                assert con.execute("SELECT code FROM postal_codes WHERE postal_code='66300'").fetchone()[0]=='66134'
                assert con.execute("SELECT code FROM postal_codes WHERE postal_code='76600'").fetchone()[0]=='76351'
                assert con.execute("SELECT code FROM postal_codes WHERE postal_code='76610'").fetchone()[0]=='76351'
                assert con.execute("SELECT value FROM metadata WHERE key='postal_source_encoding'").fetchone()[0] in (enc,'cp1252')
            print('OK',enc,n,'correspondances')
        # Un téléchargement incomplet ne doit jamais effacer l'import fonctionnel.
        source.write_bytes(b'Code_commune_INSEE;Code_postal\n66134;66300\n')
        try:
            mod.run(source,db,'test-invalid')
            raise AssertionError('Import incomplet accepté')
        except ValueError as exc:
            assert 'insuffisantes' in str(exc)
        with sqlite3.connect(db) as con:
            assert con.execute('SELECT COUNT(*) FROM postal_codes').fetchone()[0]==31003
        print('OK rollback transactionnel : 31 003 correspondances conservées')
    print('OK — import V7.3 UTF-8 et CP1252 validé.')

if __name__=='__main__':
    main()
