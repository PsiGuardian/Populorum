#!/usr/bin/env python3
"""Offline regression for the fresh-install-only national DECP v2 pipeline."""
from __future__ import annotations

import importlib.util
import json
from pathlib import Path
import shutil
import sqlite3
import subprocess
import sys
import tempfile

HERE=Path(__file__).resolve().parents[1]/'tools'
spec=importlib.util.spec_from_file_location('pipeline',HERE/'decp_pipeline.py')
p=importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)


def save(path:Path, records:list[dict]):
    path.write_text(json.dumps({'marches':{'marche':records,'contrat-concession':[]}},
                               ensure_ascii=False),encoding='utf-8')


def record(identifier:str):
    return {'id':identifier,'acheteur':{'id':'21350068900251'},'codeCPV':'90500000',
            'objet':'Collecte et traitement des déchets','montant':35120.50,
            'dateNotification':'2024-01-01','datePublicationDonnees':'2026-08-01',
            'lieuExecution':{'typeCode':'Code département','code':'35'},
            'titulaires':[{'titulaire':{'typeIdentifiant':'SIRET','id':'35005058900240'}}]}


def test_fresh_build(home:Path):
    (home/'tools').mkdir()
    shutil.copyfile(HERE/'import_decp.py',home/'tools/import_decp.py')
    p.ROOT=home
    sources=home/'sources'
    sources.mkdir()
    first=sources/'decp-2026-07.json'
    second=sources/'decp-2026-08.json'
    save(first,[record('ALPHA-1')])
    save(second,[record('ALPHA-1'),record('ALPHA-2')])
    public=home/'public.sqlite'
    staging=home/'staging.sqlite'

    assert not public.exists() and not staging.exists()
    p.import_all(sources,staging,public)
    fresh=p.db_info(staging,deep=True)
    assert fresh['imports']==2 and fresh['contracts']==2
    assert fresh['source_records']==3
    assert not public.exists()

    # Interrupted importer is resumable but cannot become a migration.
    p.import_all(sources,staging,public)
    assert p.db_info(staging,deep=True)['source_records']==3
    assert p.fast_status(staging)['schema']=='populorum-decp-2'

    # No publication when website has not been shut down.
    try:p.publish(staging,public,False)
    except ValueError as exc:assert '--site-stopped' in str(exc)
    else:raise AssertionError('Publication without explicit confirmation')

    # An invalid JSON must never overwrite the staged valid records.
    invalid=sources/'decp-2026-09.json'
    invalid.write_text('{"marches":{"marche":[',encoding='utf-8')
    try:p.import_all(sources,staging,public)
    except subprocess.CalledProcessError:pass
    else:raise AssertionError('Truncated DECP JSON accepted')
    assert p.db_info(staging,deep=True)['source_records']==3
    invalid.unlink()

    p.publish(staging,public,True)
    assert not staging.exists()
    assert p.db_info(public,deep=True)['source_records']==3
    initial_hash=p.sha(public)

    # Once a public DB exists, NO update, replacement, copy or migration.
    for attempt in (lambda:p.make_staging(home/'fresh.sqlite',public),
                    lambda:p.import_all(sources,home/'fresh.sqlite',public),
                    lambda:p.publish(public,public,True)):
        try:attempt()
        except ValueError as exc:
            assert 'existe' in str(exc) or 'présent' in str(exc)
        else:raise AssertionError('Existing public index accepted for update')
    assert p.sha(public)==initial_hash

    # Existing foreign/legacy/empty databases must also never be overwritten.
    for name, payload in [('foreign.sqlite','CREATE TABLE foreign_data(id INTEGER)'),
                          ('legacy.sqlite',"""CREATE TABLE metadata(key TEXT PRIMARY KEY,value TEXT);
                              INSERT INTO metadata VALUES ('schema','populorum-decp-1');"""),
                          ('empty.sqlite','')]:
        existing=home/name
        if payload:
            with sqlite3.connect(existing) as db:db.executescript(payload)
        else:
            existing.touch()
        before=p.sha(existing)
        try:p.make_staging(home/(name+'.staging'),existing)
        except ValueError:pass
        else:raise AssertionError('Non-fresh SQLite source accepted: '+name)
        assert p.sha(existing)==before
    print('OK — DECP v2 built from blank directory, staged, verified and published once.')
    print('OK — no import, migration, overwrite or compatibility for existing public DB.')


def test_download_revisions(home:Path):
    source=home/'source.json'
    save(source,[record('DOWNLOAD-1')])
    data=source.read_bytes()
    folder=home/'downloads'
    folder.mkdir()
    name='decp-2026-07.json'
    endpoint='https://example.org/'+name
    offset=35
    (folder/(name+'.part')).write_bytes(data[:offset])
    (folder/(name+'.part.info')).write_text(json.dumps({'url':endpoint,'title':name}))

    class Response:
        headers={}
        def __init__(self, body, status=200, start=0):
            self.payload=body
            self.status=status
            self.pos=start
            self.headers=({'Content-Range':f'bytes {start}-{len(body)-1}/{len(body)}'}
                          if status==206 else {})
        def __enter__(self):return self
        def __exit__(self,*args):return False
        def read(self, n):
            block=self.payload[self.pos:self.pos+n]
            self.pos+=len(block)
            return block

    orig=p.request
    try:
        calls=[]
        def resume(url,headers=None):
            calls.append(headers)
            assert headers=={'Range':f'bytes={offset}-'}
            return Response(data,206,start=offset)
        p.request=resume
        result=p.one_download({'title':name,'url':endpoint,'filesize':len(data)},folder)
        assert calls and result.read_bytes()==data

        prior={'url':endpoint,'last_modified':'2026-10-01',
               'filesize':len(data),'sha256':p.sha(result)}
        def must_not_call(*args,**kwargs):
            raise AssertionError('Verified source triggered a needless download')
        p.request=must_not_call
        p.one_download({'title':name,'url':endpoint,'filesize':len(data),
                        'last_modified':'2026-10-01'},folder,prior=prior)

        newer=data.replace(b'DOWNLOAD-1',b'DOWNLOAD-9')
        assert len(newer)==len(data) and newer!=data
        p.request=lambda url,headers=None:Response(newer)
        p.one_download({'title':name,'url':endpoint,'filesize':len(data),
                        'last_modified':'2026-11-04'},folder,prior=prior)
        assert result.read_bytes()==newer
    finally:
        p.request=orig
    print('OK — HTTP Range resume and revised source of identical size.')


def main():
    with tempfile.TemporaryDirectory(prefix='populorum-decp-fresh-') as folder:
        home=Path(folder)
        test_fresh_build(home)
        test_download_revisions(home)


if __name__=='__main__':
    main()
