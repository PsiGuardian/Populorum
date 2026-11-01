#!/usr/bin/env python3
"""Tests V8, offline and isolated. Never writes project databases."""
from __future__ import annotations
import importlib.util
import json
from pathlib import Path
import shutil
import sqlite3
import subprocess
import sys
import tempfile

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('pipeline',HERE/'decp_pipeline_v8.py')
p=importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)

def save(path:Path,records:list[dict]):
    path.write_text(json.dumps({'marches':{'marche':records,'contrat-concession':[]}},ensure_ascii=False),encoding='utf-8')

def record(identifier:str):
    return {'id':identifier,'acheteur':{'id':'21350068900251'},'codeCPV':'90500000',
            'objet':'Collecte et traitement des déchets','montant':35120.50,
            'dateNotification':'2024-01-01','datePublicationDonnees':'2026-08-01',
            'lieuExecution':{'typeCode':'Code département','code':'35'},
            'titulaires':[{'titulaire':{'typeIdentifiant':'SIRET','id':'35005058900240'}}]}

def main():
    original_importer=HERE/'import_decp.py'
    if not original_importer.exists():
        # Test bundle can run in V7 project, where importer is already installed.
        raise AssertionError('import_decp.py V7 absent du dossier tools')
    with tempfile.TemporaryDirectory(prefix='populorum-v8-test-') as td:
        home=Path(td)
        (home/'tools').mkdir();shutil.copyfile(original_importer,home/'tools/import_decp.py')
        p.ROOT=home
        data=home/'sources';data.mkdir()
        first=data/'decp-2026-07.json';second=data/'decp-2026-08.json'
        save(first,[record('V8TEST-1')]);save(second,[record('V8TEST-1'),record('V8TEST-2')])
        public=home/'public.sqlite';stage=home/'staging.sqlite'
        # An empty legacy index from an archive must never block a first V8 import.
        empty_public=home/'empty-public.sqlite';empty_public.touch()
        empty_stage=home/'empty-staging.sqlite'
        p.import_all(data,empty_stage,empty_public)
        assert p.db_info(empty_stage)['contracts']==2
        assert empty_public.stat().st_size==0, 'Import altered an empty public index'
        # CLI verification/status must not reject a valid staging due to an old zero-byte public file.
        for command in ('status','verify'):
            checked=subprocess.run(
                [sys.executable,str(HERE/'decp_pipeline_v8.py'),command,
                 '--staging',str(empty_stage),'--public',str(empty_public),
                 '--download-dir',str(data)],
                check=True,capture_output=True,text=True)
            assert 'sans tables DECP' in checked.stdout and 'travail' in checked.stdout.lower()
        try:p.publish(empty_stage,empty_public,True)
        except ValueError as ex:assert 'sans tables DECP' in str(ex)
        else:raise AssertionError('Publishing over an empty public index was accepted')
        assert empty_public.stat().st_size==0
        assert p.db_info(empty_stage)['contracts']==2
        empty_stage.unlink()
        # A nonzero SQLite database with no user tables is another common archive placeholder.
        shell_public=home/'shell-public.sqlite'
        with sqlite3.connect(shell_public) as db:
            db.execute('PRAGMA user_version=1')
        assert shell_public.stat().st_size>0
        assert p.is_empty_public_placeholder(shell_public)
        shell_stage=home/'shell-staging.sqlite'
        p.import_all(data,shell_stage,shell_public)
        assert p.db_info(shell_stage)['contracts']==2
        assert shell_public.stat().st_size>0
        for command in ('status','verify'):
            checked=subprocess.run(
                [sys.executable,str(HERE/'decp_pipeline_v8.py'),command,
                 '--staging',str(shell_stage),'--public',str(shell_public),
                 '--download-dir',str(data)],
                check=True,capture_output=True,text=True)
            assert 'sans tables DECP' in checked.stdout
        try:p.publish(shell_stage,shell_public,True)
        except ValueError as ex:assert 'sans tables DECP' in str(ex)
        else:raise AssertionError('Publishing over shell SQLite was accepted')
        shell_stage.unlink()
        # A non-DECP database containing user tables must block importing from it.
        foreign=home/'foreign.sqlite'
        with sqlite3.connect(foreign) as db:
            db.execute('CREATE TABLE other_data(id INTEGER)')
        assert not p.is_empty_public_placeholder(foreign)
        try:p.import_all(data,home/'foreign-staging.sqlite',foreign)
        except ValueError as ex:assert 'Schéma DECP invalide' in str(ex)
        else:raise AssertionError('Foreign DB was silently ignored')
        subprocess.run([sys.executable,str(home/'tools/import_decp.py'),str(first),'--db',str(public)],check=True,stdout=subprocess.DEVNULL)
        assert p.db_info(public)['contracts']==1
        p.import_all(data,stage,public)
        fresh=p.db_info(stage,deep=True)
        assert fresh['contracts']==2 and fresh['imports']==2, fresh
        assert p.db_info(public)['contracts']==1, 'Import overwrote the production index'
        # Idempotent repeated import of both files, no duplicate contracts or imports
        p.import_all(data,stage,public)
        assert p.db_info(stage)['contracts']==2 and p.db_info(stage)['imports']==2
        try:p.publish(stage,public,False)
        except ValueError as ex: assert '--site-stopped' in str(ex)
        else:raise AssertionError('Publication without confirmation was accepted')
        p.publish(stage,public,True)
        assert p.db_info(public)['contracts']==2
        backups=list((home/'decp-backups').glob('public-*.sqlite'))
        assert len(backups)==1 and p.db_info(backups[0])['contracts']==1, backups
        assert not stage.exists()
        # Failed import must not corrupt the current published index
        bad=data/'decp-2026-09.json';bad.write_text('{"marches":{"marche":[',encoding='utf-8')
        try:p.import_all(data,stage,public)
        except subprocess.CalledProcessError:pass
        else:raise AssertionError('Truncated JSON was accepted')
        assert p.db_info(public)['contracts']==2
        assert p.db_info(stage)['contracts']==2
        bad.unlink()
        # HTTPS Range resume test with an in-process stub, no internet access.
        payload=first.read_bytes()
        download_dir=home/'download';download_dir.mkdir()
        name='decp-2026-07.json'
        offset=37
        (download_dir/(name+'.part')).write_bytes(payload[:offset])
        (download_dir/(name+'.part.info')).write_text(json.dumps({'url':'https://example.org/'+name,'title':name}))
        calls=[]
        class FakeResponse:
            status=206
            url='https://example.org/'+name
            headers={'Content-Range':f'bytes {offset}-{len(payload)-1}/{len(payload)}'}
            def __init__(self):self.data=payload[offset:];self.pos=0
            def __enter__(self):return self
            def __exit__(self,*args):return False
            def read(self, n):
                b=self.data[self.pos:self.pos+n];self.pos+=len(b);return b
        original=p.request
        try:
            def stub(url,headers=None):
                calls.append(headers)
                assert url=='https://example.org/'+name
                assert headers=={'Range':f'bytes={offset}-'}
                return FakeResponse()
            p.request=stub
            out=p.one_download({'title':name,'url':'https://example.org/'+name,'filesize':len(payload)},download_dir)
            assert out.read_bytes()==payload and calls
        finally:p.request=original
        print('OK — téléchargement interrompu repris via HTTP Range, octets identiques')
        print('OK — public vide ou coquille SQLite préservés, staging valide, publication refusée')
        print('OK — base étrangère non-DECP jamais ignorée silencieusement')
        print('OK — index V8 : 2 fichiers, déduplication, réimportation idempotente')
        print('OK — isolation de la base publique, publication explicite, sauvegarde restaurable')
        print('OK — un fichier tronqué annule son import sans altérer la base publique')
if __name__=='__main__':main()
