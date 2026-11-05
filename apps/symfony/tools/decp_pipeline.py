#!/usr/bin/env python3
"""DECP orchestrator: official source downloads and fresh SQLite import; no database upgrades.

This script builds a new DECP v2 index from original data. No existing public DB is read, replaced or migrated.
Run from apps/symfony: python3 tools/decp_pipeline.py all
Then stop the Symfony server, and explicitly publish with: ... publish --site-stopped
"""
from __future__ import annotations
import argparse
from contextlib import closing
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import sqlite3
import subprocess
import sys
import time
from datetime import datetime, timezone
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / 'data/research'
PUBLIC = BASE / 'public-procurement.sqlite'
STAGING = BASE / 'public-procurement-v2-staging.sqlite'
SOURCES = BASE / 'decp-sources'
API = 'https://www.data.gouv.fr/api/1/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides/'
NAME = re.compile(r'^decp-[0-9]{4}-[0-9]{2}\.json$', re.I)
REQUIRED = ('metadata','imports','contracts','participants','changes','contract_sources','concession_finance','contract_records','procurement_evidence')
USER_AGENT = 'Populorum-DECP-V2/1.0 (national public open data import)'


def stamp() -> str:
    return datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')


def sha(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open('rb') as stream:
        for block in iter(lambda: stream.read(4 * 1024 * 1024), b''):
            digest.update(block)
    return digest.hexdigest()


def request(url: str, headers: dict[str,str] | None = None):
    if not url.startswith('https://'):
        raise ValueError('URL non HTTPS refusée : ' + url)
    r = urlopen(Request(url, headers={'User-Agent': USER_AGENT, **(headers or {})}), timeout=90)
    if not r.url.startswith('https://'):
        r.close()
        raise ValueError('Redirection hors HTTPS refusée : ' + url)
    return r


def catalogue() -> list[dict]:
    with request(API) as response:
        data = json.load(response)
    candidates = {}
    for r in data.get('resources', []):
        title = str(r.get('title') or '')
        url = str(r.get('url') or '')
        if not NAME.fullmatch(title) or not url.startswith('https://'):
            continue
        # If the provider re-publishes a given month, use the latest resource metadata.
        if title not in candidates or str(r.get('last_modified') or '') > str(candidates[title].get('last_modified') or ''):
            candidates[title] = r
    if not candidates:
        raise ValueError('Aucun fichier mensuel DECP reconnu dans le catalogue officiel.')
    print(f'Catalogue officiel : {len(candidates)} fichiers mensuels disponibles.')
    return [candidates[k] for k in sorted(candidates)]


def check_json_edges(path:Path) -> bool:
    if path.stat().st_size < 2:
        return False
    with path.open('rb') as stream:
        head = stream.read(1024).lstrip(b'\xef\xbb\xbf \r\n\t')
        stream.seek(max(0,path.stat().st_size-1024))
        tail = stream.read().rstrip()
    return head.startswith(b'{') and tail.endswith(b'}')


def one_download(resource:dict, output:Path, attempts:int=4, prior:dict|None=None) -> Path:
    url=str(resource.get('url') or '')
    title=str(resource['title'])
    if not NAME.fullmatch(title):
        raise ValueError('Nom de fichier non reconnu')
    dest=output/title
    part=output/(title+'.part')
    info=output/(title+'.part.info')
    expected = int(resource.get('filesize') or 0)
    trusted=(isinstance(prior,dict)
        and prior.get('url')==url
        and prior.get('last_modified')==str(resource.get('last_modified') or '')
        and prior.get('filesize')==expected
        and isinstance(prior.get('sha256'),str))
    valid=dest.exists() and check_json_edges(dest) and (not expected or dest.stat().st_size==expected)
    if valid and trusted and sha(dest)==prior['sha256']:
        print(f'SOURCE À JOUR ET VÉRIFIÉE : {title} ({dest.stat().st_size/1e6:.1f} Mo)')
        return dest
    if dest.exists() and not valid:
        # Preserve malformed prior files for diagnostics. A valid prior version
        # stays in place until its replacement is fully transferred and verified.
        old=dest.with_name(dest.name+'.invalid-'+stamp())
        dest.replace(old)
        print(f'Ancien fichier non conforme déplacé : {old.name}')
    elif dest.exists():
        print('NOUVELLE RÉVISION OU PROVENANCE NON VÉRIFIÉE :',title)
    if part.exists():
        meta=json.loads(info.read_text()) if info.exists() else {}
        if meta.get('url') != url:
            part.unlink();info.unlink(missing_ok=True)
    if not info.exists():
        info.write_text(json.dumps({'url':url,'title':title},ensure_ascii=False))
    print('Téléchargement :', title,flush=True)
    for attempt in range(1,attempts+1):
        offset=part.stat().st_size if part.exists() else 0
        if expected and offset>expected:
            part.unlink();offset=0
        headers={'Range':f'bytes={offset}-'} if offset else {}
        try:
            with request(url,headers) as response:
                status=response.status
                if offset and status == 206:
                    start=response.headers.get('Content-Range','').split(' ')[-1].split('-')[0]
                    if not start.isdecimal() or int(start)!=offset:
                        raise ValueError('Réponse HTTP Range incohérente')
                    mode='ab'
                elif status == 200:
                    # A server may ignore Range; restart from zero, never append a full file.
                    mode='wb'
                else:
                    raise ValueError(f'Réponse HTTP inattendue : {status}')
                with part.open(mode) as target:
                    while True:
                        chunk=response.read(1024*1024)
                        if not chunk:break
                        target.write(chunk)
                    target.flush();os.fsync(target.fileno())
            if expected and part.stat().st_size!=expected:
                raise OSError(f'Taille incomplète : {part.stat().st_size} / {expected}')
            if not check_json_edges(part):
                raise ValueError('Début ou fin du fichier JSON incorrecte; téléchargement conservé pour diagnostic')
            os.replace(part,dest)
            info.unlink(missing_ok=True)
            print(f'OK {title} — {dest.stat().st_size/1e6:.1f} Mo, SHA-256 {sha(dest)[:16]}')
            return dest
        except (HTTPError, URLError, TimeoutError, OSError, ValueError) as exc:
            print(f'Transfert interrompu ({attempt}/{attempts}) : {exc}',file=sys.stderr,flush=True)
            if attempt==attempts:raise ValueError(f'Téléchargement non terminé : {title}. Relancer la même commande pour reprendre.') from exc
            time.sleep(min(2**attempt,10))
    raise RuntimeError('Inaccessible')


def download_all(folder:Path=SOURCES) -> list[Path]:
    folder.mkdir(parents=True,exist_ok=True)
    items=catalogue()
    paths=[]
    meta_path=folder/'resource-catalog-meta.json'
    previous=json.loads(meta_path.read_text(encoding='utf-8')) if meta_path.exists() else {}
    if not isinstance(previous,dict):
        raise ValueError('Manifest de révision des ressources invalide.')
    revisions={}
    for n,r in enumerate(items,1):
        print(f'[{n}/{len(items)}] ',end='',flush=True)
        dest=one_download(r,folder,prior=previous.get(r['title']))
        paths.append(dest)
        revisions[r['title']]={
            'url':str(r['url']),
            'last_modified':str(r.get('last_modified') or ''),
            'filesize':int(r.get('filesize') or 0),
            'sha256':sha(dest),
        }
        # Persist each verified resource to support a safe resume after a failure.
        meta_temp=folder/'resource-catalog-meta.json.part'
        meta_temp.write_text(json.dumps(revisions,ensure_ascii=False,indent=2),encoding='utf-8')
        os.replace(meta_temp,meta_path)
    # Manifest holds the genuine catalog resource URLs, never guessed links.
    manifest={str(r['title']):str(r['url']) for r in items}
    temporary=folder/'resource-catalog.json.part'
    temporary.write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf-8')
    os.replace(temporary,folder/'resource-catalog.json')
    print(f'{len(paths)} ressources téléchargées dans {folder}')
    return paths


def db_info(path:Path, deep:bool=False) -> dict:
    if not path.is_file():raise ValueError('Base absente : '+str(path))
    with closing(sqlite3.connect(f'file:{path.resolve()}?mode=ro',uri=True,timeout=30)) as db:
        tables={r[0] for r in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}
        if not set(REQUIRED).issubset(tables):
            raise ValueError('Schéma DECP invalide : '+','.join(sorted(set(REQUIRED)-tables)))
        ver=db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
        if ver is None or ver[0]!='populorum-decp-2':raise ValueError('Version du schéma inconnue')
        integrity=db.execute('PRAGMA integrity_check' if deep else 'PRAGMA quick_check').fetchone()[0]
        if integrity!='ok':raise ValueError('Intégrité SQLite : '+integrity)
        result={k: db.execute('SELECT COUNT(*) FROM '+k).fetchone()[0] for k in ('imports','contracts','participants','changes')}
        if deep:
            expected=db.execute('SELECT COALESCE(SUM(records),0) FROM imports').fetchone()[0]
            actual=db.execute('SELECT COUNT(*) FROM contract_records').fetchone()[0]
            if expected!=actual:
                raise ValueError(f'Archives sources incomplètes : {actual} déclarations sur {expected} attendues.')
            missing=db.execute('''SELECT COUNT(*) FROM contract_records r
                LEFT JOIN contracts c ON c.contract_key=r.contract_key
                WHERE c.contract_key IS NULL''').fetchone()[0]
            if missing:
                raise ValueError(f'Déclarations sans contrat indexé : {missing}.')
            result['source_records']=actual
    result['bytes']=path.stat().st_size
    return result



def fast_status(path:Path) -> dict:
    """Cheap metadata/readability check for a trusted, already imported index.

    This is NOT a full SQLite integrity check. Use 'verify' for in-depth checks.
    Avoid scanning every page of a multi-GB database for the routine status command.
    """
    if not path.is_file():
        raise ValueError('Base absente : '+str(path))
    if path.stat().st_size < 4096:
        raise ValueError('Index vide ou incomplet : '+str(path))
    with closing(sqlite3.connect(f'file:{path.resolve()}?mode=ro',uri=True,timeout=30)) as db:
        tables={row[0] for row in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}
        missing=set(REQUIRED)-tables
        if missing:
            raise ValueError('Schéma DECP incomplet : '+','.join(sorted(missing)))
        version=db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
        if version is None or version[0]!='populorum-decp-2':
            raise ValueError('Version du schéma DECP incompatible')
        # EXISTS/LIMIT 1 reads at most the first matching leaf of each table.
        has_imports=db.execute('SELECT EXISTS(SELECT 1 FROM imports LIMIT 1)').fetchone()[0]
        has_contracts=db.execute('SELECT EXISTS(SELECT 1 FROM contracts LIMIT 1)').fetchone()[0]
        if not has_imports or not has_contracts:
            raise ValueError('Aucun import ou contrat dans la base nationale')
    return {'bytes':path.stat().st_size, 'schema':'populorum-decp-2',
            'imports_present':bool(has_imports), 'contracts_present':bool(has_contracts),
            'integrity_scan':'not_run (optional: verify)'}


def make_staging(staging:Path=STAGING, public:Path=PUBLIC):
    """Fresh-install only: resume a staging index, never read/copy an older public DB."""
    if public.exists() or public.is_symlink():
        raise ValueError(
            'Un index DECP public existe déjà : cette version exige une installation neuve. '
            'Utiliser un dossier de destination vide; aucun index existant n’est remplacé.'
        )
    if staging.exists() or staging.is_symlink():
        db_info(staging)
        print('Reprise d’un index de travail DECP v2 déjà vérifié :',staging)
        return
    staging.parent.mkdir(parents=True,exist_ok=True)
    print('Création de l’index DECP v2 neuf dans :',staging)


def checkpoint_stage(path:Path):
    if not path.exists():return
    with closing(sqlite3.connect(path,timeout=120)) as db:
        db.execute('PRAGMA busy_timeout=120000')
        cursor=db.execute('PRAGMA wal_checkpoint(TRUNCATE)')
        checkpoint=cursor.fetchone()
        cursor.close()
        if checkpoint and checkpoint[0]!=0:
            raise ValueError('Lecteurs actifs sur la base de travail')
        mode=db.execute('PRAGMA journal_mode=DELETE').fetchone()[0]
        if mode.lower()!='delete':raise ValueError('Journal staging non converti en DELETE : '+mode)


def import_all(folder:Path=SOURCES,staging:Path=STAGING,public:Path=PUBLIC):
    files=sorted(p for p in folder.glob('decp-????-??.json') if p.is_file())
    if not files:raise ValueError('Aucun DECP JSON téléchargé dans '+str(folder))
    make_staging(staging,public)
    manifest_path=folder/'resource-catalog.json'
    source_urls=json.loads(manifest_path.read_text(encoding='utf-8')) if manifest_path.exists() else {}
    if not isinstance(source_urls,dict):
        raise ValueError('Manifeste des sources DECP invalide')
    importer=ROOT/'tools/import_decp.py'
    if not importer.exists():raise ValueError('Importeur DECP v2 manquant : '+str(importer))
    for n,p in enumerate(files,1):
        print(f'IMPORT [{n}/{len(files)}] {p.name}',flush=True)
        cmd=[sys.executable,str(importer),str(p),'--db',str(staging)]
        if str(source_urls.get(p.name,'')).startswith('https://'):
            cmd+=['--source-url',source_urls[p.name]]
        subprocess.run(cmd,check=True)
    # Publish only precisely referenced, curated documents whose contract is
    # actually present. Never fabricate associations for absent contracts.
    curated=ROOT/'data/curated-procurement'
    if curated.is_dir():
        # Tests load this pipeline with importlib by path, so resolve our sibling
        # maintained tool explicitly instead of depending on sys.path.
        from importlib.util import spec_from_file_location, module_from_spec
        spec=spec_from_file_location('populorum_evidence',Path(__file__).with_name('import_procurement_evidence.py'))
        if spec is None or spec.loader is None:
            raise ValueError('Importeur de preuves documentaires indisponible.')
        module=module_from_spec(spec)
        spec.loader.exec_module(module)
        with closing(sqlite3.connect(staging,timeout=30)) as db:
            for evidence_file in sorted(curated.glob('*.json')):
                items=json.loads(evidence_file.read_text(encoding='utf-8'))
                if not isinstance(items,list):
                    raise ValueError('Dossier documentaire invalide : '+str(evidence_file))
                matched=[]
                for item in items:
                    key=item.get('contract_key') if isinstance(item,dict) else ''
                    if not key or not db.execute('SELECT 1 FROM contracts WHERE contract_key=?',(key,)).fetchone():
                        print('Référence non importée; document non associé :',evidence_file.name,key)
                        continue
                    matched.append(item)
                if matched:
                    added=module.import_evidence(db,matched)
                    db.commit()
                    print('DOCUMENTS SOURCÉS :',evidence_file.name,added,'ajoutés.')
    checkpoint_stage(staging)
    print('BASE DE TRAVAIL VÉRIFIÉE :',db_info(staging,deep=True))
    print('Index de travail prêt. Arrêter le serveur avant publish --site-stopped (destination publique initialement absente).')


def publish(staging:Path=STAGING, public:Path=PUBLIC, stopped:bool=False):
    """Publish a verified fresh index without replacing any existing file."""
    if not stopped:
        raise ValueError('Publication refusée : arrêter le serveur puis passer --site-stopped.')
    if public.exists() or public.is_symlink():
        raise ValueError(
            'Index DECP public déjà présent : publication réservée à une installation neuve.'
        )
    if not staging.exists() or staging.is_symlink():
        raise ValueError('Index de travail absent ou invalide : lancer import puis verify.')
    checkpoint_stage(staging)
    db_info(staging,deep=True)
    public.parent.mkdir(parents=True,exist_ok=True)
    # An atomic hard link refuses any existing destination, even if another
    # process created it between the prior existence check and publication.
    # Both files live in the same data/research filesystem.
    try:
        os.link(staging,public)
    except FileExistsError as exc:
        raise ValueError('Index public créé entretemps : aucun écrasement autorisé.') from exc
    staging.unlink()
    print('INDEX DECP v2 INSTALLÉ POUR LA PREMIÈRE FOIS :',public)
    print('Statistiques :',db_info(public))


def main():
    parser=argparse.ArgumentParser(description=__doc__,formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('command',choices=('download','import','all','verify','publish','status'))
    parser.add_argument('--download-dir',type=Path,default=SOURCES)
    parser.add_argument('--staging',type=Path,default=STAGING)
    parser.add_argument('--public',type=Path,default=PUBLIC)
    parser.add_argument('--site-stopped',action='store_true',help='Confirmation : site arrêté durant la publication')
    opts=parser.parse_args()
    if opts.command in ('download','all'):
        download_all(opts.download_dir)
    if opts.command in ('import','all'):
        import_all(opts.download_dir,opts.staging,opts.public)
    if opts.command=='verify':
        print('Base de travail :',db_info(opts.staging,deep=True))
        if opts.public.exists():
            print('Index public déjà présent : ce pipeline ne le remplace jamais.')
    if opts.command=='publish':publish(opts.staging,opts.public,opts.site_stopped)
    if opts.command=='status':
        for name,p in [('publique',opts.public),('travail',opts.staging)]:
            if not p.exists():
                print(name,'absente')
            else:
                try:
                    print(name,p,fast_status(p))
                except (ValueError,sqlite3.DatabaseError) as exc:
                    print(name,p,'INDEX INVALIDE :',exc)
        files=list(opts.download_dir.glob('decp-????-??.json')) if opts.download_dir.exists() else []
        partial=list(opts.download_dir.glob('*.part')) if opts.download_dir.exists() else []
        print('Sources téléchargées :',len(files),'sources partielles :',len(partial))

if __name__=='__main__':
    try:main()
    except (ValueError,OSError,sqlite3.DatabaseError,subprocess.CalledProcessError) as exc:
        sys.exit('ÉCHEC : '+str(exc))
