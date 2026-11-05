#!/usr/bin/env python3
"""DECP importer: streaming, repeatable and source-aware; uses only Python stdlib.

Usage from apps/symfony:
 python3 tools/import_decp.py /path/to/decp-2026-08.json
 python3 tools/import_decp.py /path/to/42-json-files/ --all-files
 python3 tools/import_decp.py --download-all --max-gb 4  # network, large download
 python3 tools/import_decp.py --stats

Source https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides
NOTE: an awarded contract is NEVER treated as a payment or an accounting expenditure.
"""
from __future__ import annotations
import argparse
from datetime import datetime, timezone
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
import hashlib
import json
from pathlib import Path
import re
import shutil
import sqlite3
import sys
import tempfile
import unicodedata
import zlib
from urllib.request import Request, urlopen
from typing import Mapping, Any

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DB = ROOT / 'data/research/public-procurement.sqlite'
DATASET = 'https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides'
API_URL = 'https://www.data.gouv.fr/api/1/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides/'
BUFFER = 262144

HISTORY_SCHEMA = '''
CREATE TABLE IF NOT EXISTS contract_publications (
  contract_key TEXT NOT NULL,
  source_sha TEXT NOT NULL,
  declared_publication_date TEXT NOT NULL,
  notice_date TEXT NOT NULL,
  base_amount_cents INTEGER,
  modification_amount_cents INTEGER,
  occurrences INTEGER NOT NULL DEFAULT 1,
  PRIMARY KEY (contract_key, source_sha)
);
CREATE INDEX IF NOT EXISTS publications_contract ON contract_publications(contract_key, source_sha);
CREATE INDEX IF NOT EXISTS publications_source ON contract_publications(source_sha);
'''


def ensure_history_schema(db: sqlite3.Connection) -> None:
    db.executescript(HISTORY_SCHEMA)


def observe(db: sqlite3.Connection, key: str, sha: str, item: Mapping[str, Any], kind: str, cents) -> None:
    # A modification may report a change amount rather than the new contract total.
    # Never add it to the base amount and never label it as paid.
    amount = cents(item.get('montant') if kind == 'market' else item.get('valeurGlobale'))
    modified = None
    for wrapper in item.get('modifications') or []:
        modification = wrapper.get('modification') if isinstance(wrapper, dict) else None
        if isinstance(modification, dict):
            value = cents(modification.get('montant'))
            if value is not None:
                modified = value
    published = str(item.get('datePublicationDonnees') or '')[:30]
    notice = str(item.get('dateNotification') or item.get('dateSignature') or '')[:30]
    db.execute('''INSERT INTO contract_publications
      (contract_key,source_sha,declared_publication_date,notice_date,
       base_amount_cents,modification_amount_cents,occurrences)
       VALUES(?,?,?,?,?,?,1)
       ON CONFLICT(contract_key,source_sha) DO UPDATE SET
         occurrences=contract_publications.occurrences+1,
         declared_publication_date=CASE WHEN excluded.declared_publication_date >= contract_publications.declared_publication_date
             THEN excluded.declared_publication_date ELSE contract_publications.declared_publication_date END,
         notice_date=CASE WHEN excluded.declared_publication_date >= contract_publications.declared_publication_date
             THEN excluded.notice_date ELSE contract_publications.notice_date END,
         base_amount_cents=CASE WHEN excluded.declared_publication_date >= contract_publications.declared_publication_date
             THEN COALESCE(excluded.base_amount_cents,contract_publications.base_amount_cents)
             ELSE contract_publications.base_amount_cents END,
         modification_amount_cents=CASE WHEN excluded.declared_publication_date >= contract_publications.declared_publication_date
             THEN COALESCE(excluded.modification_amount_cents,contract_publications.modification_amount_cents)
             ELSE contract_publications.modification_amount_cents END''',
        (key, sha, published, notice, amount, modified))


SCHEMA = '''
CREATE TABLE IF NOT EXISTS metadata(key TEXT PRIMARY KEY,value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS imports(
 sha256 TEXT PRIMARY KEY,filename TEXT NOT NULL,source_url TEXT NOT NULL,
 imported_at TEXT NOT NULL,records INTEGER NOT NULL,contracts_new INTEGER NOT NULL,
 events INTEGER NOT NULL,subcontractors INTEGER NOT NULL,bytes INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS contracts(
 contract_key TEXT PRIMARY KEY,kind TEXT NOT NULL,buyer_id TEXT NOT NULL,
 original_id TEXT NOT NULL,cpv TEXT NOT NULL,nature TEXT NOT NULL,object TEXT NOT NULL,
 procedure TEXT NOT NULL,notice_date TEXT NOT NULL,published_date TEXT NOT NULL,
 duration_months INTEGER,offer_count INTEGER,amount_initial_cents INTEGER,
 amount_amended_cents INTEGER,location_type TEXT NOT NULL,location_code TEXT NOT NULL,
 source_name TEXT NOT NULL,source_sha TEXT NOT NULL,source_snapshot TEXT NOT NULL,
 unique_records INTEGER NOT NULL DEFAULT 1,
 CHECK(kind IN ('market','concession')));
CREATE TABLE IF NOT EXISTS participants(
 contract_key TEXT NOT NULL,role TEXT NOT NULL,identifier_type TEXT NOT NULL,
 identifier TEXT NOT NULL,PRIMARY KEY(contract_key,role,identifier_type,identifier));
CREATE TABLE IF NOT EXISTS changes(
 event_key TEXT PRIMARY KEY,contract_key TEXT NOT NULL,event_type TEXT NOT NULL,
 event_id TEXT NOT NULL,event_date TEXT NOT NULL,published_date TEXT NOT NULL,
 amount_cents INTEGER,identifier TEXT NOT NULL,extra TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS contract_sources(
 contract_key TEXT NOT NULL,source_sha TEXT NOT NULL,
 PRIMARY KEY(contract_key,source_sha));
CREATE TABLE IF NOT EXISTS concession_finance(
 contract_key TEXT PRIMARY KEY,subsidy_cents INTEGER,investment_cents INTEGER,
 investment_note TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS contracts_buyer ON contracts(buyer_id,kind,notice_date);
CREATE INDEX IF NOT EXISTS contracts_date ON contracts(notice_date,contract_key);
CREATE INDEX IF NOT EXISTS contracts_geo ON contracts(location_type,location_code);
CREATE INDEX IF NOT EXISTS contracts_kind ON contracts(kind,notice_date);
CREATE INDEX IF NOT EXISTS participants_lookup ON participants(identifier,role);
CREATE INDEX IF NOT EXISTS changes_contract ON changes(contract_key,event_date);
-- Every original declaration is independently retrievable and auditable.
CREATE TABLE IF NOT EXISTS contract_records(
 source_sha TEXT NOT NULL,kind TEXT NOT NULL,ordinal INTEGER NOT NULL,
 contract_key TEXT NOT NULL,record_sha TEXT NOT NULL,record_zlib BLOB NOT NULL,
 PRIMARY KEY(source_sha,kind,ordinal));
CREATE INDEX IF NOT EXISTS contract_records_key ON contract_records(contract_key);
CREATE TABLE IF NOT EXISTS procurement_evidence(
 evidence_id TEXT PRIMARY KEY,contract_key TEXT NOT NULL,
 category TEXT NOT NULL,source_type TEXT NOT NULL,source_name TEXT NOT NULL,
 source_url TEXT NOT NULL,document_title TEXT NOT NULL,
 statement TEXT NOT NULL,excerpt TEXT NOT NULL,published_at TEXT NOT NULL,
 recorded_at TEXT NOT NULL,status TEXT NOT NULL,
 CHECK(category IN ('context','purpose','scope','selection','funding','payment','outcome')),
 CHECK(status IN ('candidate','documented')));
CREATE INDEX IF NOT EXISTS procurement_evidence_key ON procurement_evidence(contract_key,category,status);
'''


def ensure_fts(db):
    """Maintain searchable contract text across future monthly imports, with FTS5."""
    try:
        db.executescript('''
          CREATE VIRTUAL TABLE IF NOT EXISTS contract_fts USING fts5(
            object, original_id, content='contracts', content_rowid='rowid',
            tokenize='unicode61 remove_diacritics 2');
          CREATE TRIGGER IF NOT EXISTS contracts_ai AFTER INSERT ON contracts BEGIN
            INSERT INTO contract_fts(rowid,object,original_id) VALUES(new.rowid,new.object,new.original_id);
          END;
          CREATE TRIGGER IF NOT EXISTS contracts_ad AFTER DELETE ON contracts BEGIN
            INSERT INTO contract_fts(contract_fts,rowid,object,original_id)
                VALUES('delete',old.rowid,old.object,old.original_id);
          END;
          CREATE TRIGGER IF NOT EXISTS contracts_au AFTER UPDATE ON contracts BEGIN
            INSERT INTO contract_fts(contract_fts,rowid,object,original_id)
                VALUES('delete',old.rowid,old.object,old.original_id);
            INSERT INTO contract_fts(rowid,object,original_id) VALUES(new.rowid,new.object,new.original_id);
          END;
        ''')
        if not db.execute("SELECT 1 FROM metadata WHERE key='fts_ready'").fetchone():
            db.execute("INSERT INTO contract_fts(contract_fts) VALUES('rebuild')")
            db.execute("INSERT OR REPLACE INTO metadata VALUES('fts_ready','1')")
            db.commit()
    except sqlite3.OperationalError as exc:
        raise ValueError('SQLite FTS5 est requis pour la recherche nationale: '+str(exc)) from exc

def sha_file(path:Path)->str:
    h=hashlib.sha256()
    with path.open('rb') as f:
        for b in iter(lambda:f.read(4*1024*1024),b''):h.update(b)
    return h.hexdigest()

def stream_array(path:Path,key:str):
    """Decode one JSON object at a time from a named top-level array.

    This is independent of ijson and does not load a 1GB consolidated file.
    Assumes the DECP wrapper with the array under marches.{marche,contrat-concession}.
    """
    marker=re.compile(r'"'+re.escape(key)+r'"\s*:\s*\[')
    decoder=json.JSONDecoder()
    with path.open('r',encoding='utf-8-sig') as fd:
        buf=''; pos=0; begun=False; ended=False
        while True:
            if not begun:
                buf+=fd.read(BUFFER)
                match=marker.search(buf)
                if match:
                    pos=match.end();begun=True
                else:
                    if len(buf)>2048:buf=buf[-2048:]
                    if not buf or fd.tell()==path.stat().st_size:break
                    continue
            while True:
                while pos<len(buf) and buf[pos] in '\r\n\t ,':pos+=1
                if pos<len(buf) and buf[pos]==']':
                    ended=True;break
                if pos>=len(buf):break
                try: obj,end=decoder.raw_decode(buf,pos)
                except ValueError:
                    if len(buf)-pos>32_000_000:
                        raise ValueError('Objet JSON de plus de 32 Mo ou entrée malformée')
                    break
                if not isinstance(obj,dict):raise ValueError('Objet non dictionnaire dans '+key)
                yield obj
                pos=end
                if pos>BUFFER:
                    buf=buf[pos:];pos=0
            if ended:break
            chunk=fd.read(BUFFER)
            if not chunk:break
            if pos:
                buf=buf[pos:]+chunk;pos=0
            else:buf+=chunk
        if not begun:raise ValueError('Tableau '+key+' absent dans '+str(path))
        if not ended:raise ValueError('Fichier JSON tronqué dans '+key)

def cents(raw):
    if raw is None or isinstance(raw,bool):return None
    try:
        d=Decimal(str(raw))
        if not d.is_finite() or abs(d)>Decimal('100000000000000'):return None
        return int((d*100).quantize(Decimal('1'),rounding=ROUND_HALF_UP))
    except (InvalidOperation,ValueError,TypeError):return None

def txt(v, maxlen=800):return str(v or '').strip()[:maxlen]
def number(v):
    try:return int(v)
    except (ValueError,TypeError):return None

def get_participants(data,name,inner):
    for item in data.get(name) or []:
        if not isinstance(item,dict):continue
        child=item.get(inner) or {}
        if isinstance(child,dict):
            id_=txt(child.get('id'),80)
            if id_:yield txt(child.get('typeIdentifiant'),30),id_

def identifier(data):
    return txt((data or {}).get('id') if isinstance(data,dict) else '',80)

def contract_key(rec,kind):
    """DECP v2: avoid collapsing lots where the published procedure ID is reused.

    This is a *record-family* fingerprint, not a claim that two versions of the
    same procurement have been legally matched. Changed descriptions or holders
    may create separate families; their reconciliation requires documentary proof.
    """
    buyer=identifier(rec.get('acheteur') if kind=='market' else rec.get('autoriteConcedante'))
    cid=txt(rec.get('id'),160)
    cpv=txt(rec.get('codeCPV'),20) if kind=='market' else ''
    if not buyer or not cid:return None
    def norm(value):
        return ' '.join(unicodedata.normalize('NFKC',str(value or '')).casefold().split())
    participants=(
        get_participants(rec,'titulaires','titulaire') if kind=='market'
        else get_participants(rec,'concessionnaires','concessionnaire')
    )
    holders='|'.join(sorted(f'{type_}:{ident}' for type_,ident in participants))
    # Deliberate conservative split for ambiguous original IDs: avoid false merges.
    basis=['decp-v2',kind,buyer,cid,cpv,norm(rec.get('objet')),
           txt(rec.get('dateNotification') or rec.get('dateSignature'),30),holders]
    return hashlib.sha256('\\0'.join(basis).encode('utf-8')).hexdigest()[:40]


def loc(rec):
    obj=rec.get('lieuExecution') or {}
    return txt(obj.get('typeCode'),45),txt(obj.get('code'),20)

def import_file(db,path:Path,source_url:str='',min_records:int=1):
    path=path.resolve()
    if not path.is_file():raise ValueError('Fichier non trouvé: '+str(path))
    digest=sha_file(path)
    if db.execute('SELECT 1 FROM imports WHERE sha256=?',(digest,)).fetchone():
        print('DÉJÀ IMPORTÉ :',path.name,'(SHA-256 identique)');return False
    total=0;added=0;events=0;subcontractors=0;seen_keys=set()
    db.execute('BEGIN IMMEDIATE')
    try:
        for group,kind in [('marche','market'),('contrat-concession','concession')]:
            for ordinal,item in enumerate(stream_array(path,group),start=1):
                key=contract_key(item,kind)
                if not key:continue
                total+=1
                raw=json.dumps(item,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode('utf-8')
                record_sha=hashlib.sha256(raw).hexdigest()
                db.execute('''INSERT INTO contract_records
                    (source_sha,kind,ordinal,contract_key,record_sha,record_zlib)
                    VALUES (?,?,?,?,?,?)''',
                    (digest,kind,ordinal,key,record_sha,sqlite3.Binary(zlib.compress(raw,6))))
                if total%15000==0:print(f'  {total:,} enregistrements parcourus...',flush=True)
                buyer=identifier(item.get('acheteur') if kind=='market' else item.get('autoriteConcedante'))
                location_type,location_code=loc(item)
                base=cents(item.get('montant') if kind=='market' else item.get('valeurGlobale'))
                latest=None
                for mod in (item.get('modifications') or []):
                    m=mod.get('modification') if isinstance(mod,dict) else {}
                    if not isinstance(m,dict):continue
                    val=cents(m.get('montant'))
                    if val is not None:latest=val
                cur=db.execute('SELECT notice_date,published_date,object FROM contracts WHERE contract_key=?',(key,)).fetchone()
                if cur is None:
                    db.execute('''INSERT INTO contracts(contract_key,kind,buyer_id,original_id,cpv,nature,object,procedure,
                        notice_date,published_date,duration_months,offer_count,amount_initial_cents,amount_amended_cents,
                        location_type,location_code,source_name,source_sha,source_snapshot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''',
                        (key,kind,buyer,txt(item.get('id'),160),txt(item.get('codeCPV'),20),txt(item.get('nature'),90),
                         txt(item.get('objet'),2500),txt(item.get('procedure'),150),txt(item.get('dateNotification') or item.get('dateSignature'),30),
                         txt(item.get('datePublicationDonnees'),30),number(item.get('dureeMois')),number(item.get('offresRecues')),
                         base,latest,location_type,location_code,txt(item.get('source'),90),digest,path.name))
                    added+=1
                else:
                    # A more recent publication may be a correction; never sum overlapping snapshots.
                    old_notice,old_pub,old_obj=cur
                    pub=txt(item.get('datePublicationDonnees'),30)
                    if pub>=old_pub and len(txt(item.get('objet'),2500))>=10:
                        db.execute('''UPDATE contracts SET object=?,procedure=?,published_date=?,notice_date=?,
                            duration_months=COALESCE(?,duration_months),offer_count=COALESCE(?,offer_count),
                            amount_initial_cents=COALESCE(?,amount_initial_cents),
                            amount_amended_cents=COALESCE(?,amount_amended_cents),location_type=?,location_code=?,
                            source_name=?,source_sha=?,source_snapshot=? WHERE contract_key=?''',
                            (txt(item.get('objet'),2500),txt(item.get('procedure'),150),pub,txt(item.get('dateNotification') or item.get('dateSignature'),30),
                             number(item.get('dureeMois')),number(item.get('offresRecues')),base,latest,location_type,location_code,
                             txt(item.get('source'),90),digest,path.name,key))
                db.execute('INSERT OR IGNORE INTO contract_sources(contract_key,source_sha) VALUES (?,?)',(key,digest))
                observe(db,key,digest,item,kind,cents)
                for role, arr, inner in [('holder','titulaires','titulaire'),('concessionaire','concessionnaires','concessionnaire')]:
                    for tp,val in get_participants(item,arr,inner):
                        db.execute('INSERT OR IGNORE INTO participants VALUES (?,?,?,?)',(key,role,tp,val))
                for m in (item.get('modifications') or []):
                    d=m.get('modification') if isinstance(m,dict) else None
                    if not isinstance(d,dict):continue
                    eid=txt(d.get('id'),120)
                    dt=txt(d.get('dateNotificationModification') or d.get('dateNotification'),30)
                    amt=cents(d.get('montant'))
                    sig=hashlib.sha256(json.dumps(d,sort_keys=True,ensure_ascii=False).encode()).hexdigest()[:24]
                    event='mod-'+hashlib.sha256((key+'|'+eid+'|'+sig).encode()).hexdigest()[:40]
                    cursor=db.execute('INSERT OR IGNORE INTO changes VALUES (?,?,?,?,?,?,?,?,?)',
                       (event,key,'amendment',eid,dt,txt(d.get('datePublicationDonneesModification'),30),amt,'',json.dumps({'dureeMois':d.get('dureeMois')},ensure_ascii=False)))
                    events+=cursor.rowcount
                    for tp,val in get_participants(d,'titulaires','titulaire'):
                        db.execute('INSERT OR IGNORE INTO participants VALUES (?,?,?,?)',(key,'holder_amended',tp,val))
                for m in (item.get('actesSousTraitance') or []):
                    d=m.get('acteSousTraitance') if isinstance(m,dict) else None
                    if not isinstance(d,dict):continue
                    subcontractor=d.get('sousTraitant') or {}
                    sid=identifier(subcontractor)
                    eid=txt(d.get('id'),120)
                    dt=txt(d.get('dateNotification'),30)
                    sig=hashlib.sha256(json.dumps(d,sort_keys=True,ensure_ascii=False).encode()).hexdigest()[:24]
                    event='sub-'+hashlib.sha256((key+'|'+eid+'|'+sig).encode()).hexdigest()[:40]
                    cursor=db.execute('INSERT OR IGNORE INTO changes VALUES (?,?,?,?,?,?,?,?,?)',
                        (event,key,'subcontract',eid,dt,txt(d.get('datePublicationDonnees'),30),cents(d.get('montant')),sid,
                         json.dumps({'dureeMois':d.get('dureeMois'),'variationPrix':d.get('variationPrix')},ensure_ascii=False)))
                    events+=cursor.rowcount;subcontractors+=cursor.rowcount
                    if sid:db.execute('INSERT OR IGNORE INTO participants VALUES (?,?,?,?)',
                        (key,'subcontractor',txt(subcontractor.get('typeIdentifiant'),30),sid))
                if kind=='concession':
                    investment=0;known=False
                    for entry in item.get('donneesExecution') or []:
                        d=entry.get('donneesAnnuelles') if isinstance(entry,dict) else {}
                        v=cents((d or {}).get('depensesInvestissement'))
                        if v is not None:investment+=v;known=True
                    db.execute('''INSERT INTO concession_finance(contract_key,subsidy_cents,investment_cents,investment_note)
                       VALUES(?,?,?,?) ON CONFLICT(contract_key) DO UPDATE SET
                       subsidy_cents=COALESCE(excluded.subsidy_cents,subsidy_cents),
                       investment_cents=COALESCE(excluded.investment_cents,investment_cents),
                       investment_note=excluded.investment_note''',
                       (key,cents(item.get('montantSubventionPublique')),investment if known else None,
                        'Somme des données annuelles déclarées dans la publication; peut se chevaucher entre versions.'))
        if total<min_records:raise ValueError(f'Import insuffisant : {total} enregistrements')
        db.execute('INSERT INTO imports VALUES(?,?,?,?,?,?,?,?,?)',(digest,path.name,source_url or DATASET,
            datetime.now(timezone.utc).isoformat(timespec='seconds'),total,added,events,subcontractors,path.stat().st_size))
        db.execute('INSERT OR REPLACE INTO metadata VALUES(?,?)',('schema','populorum-decp-2'))
        db.commit()
    except BaseException:
        db.rollback();raise
    print(f'OK {path.name}: {total} enregistrements, {added} contrats ajoutés, {events} événements nouveaux, {subcontractors} sous-traitances; SHA {digest[:14]}')
    return True

def download_all(folder:Path,max_gb:float):
    folder.mkdir(parents=True,exist_ok=True)
    with urlopen(Request(API_URL,headers={'User-Agent':'Populorum-DECP/1.0'}),timeout=60) as r:
        data=json.load(r)
    resources=[r for r in data.get('resources',[]) if re.fullmatch(r'decp-\d{4}-\d{2}\.json',r.get('title',''),re.I)]
    print(f'{len(resources)} ressources mensuelles DECP annoncées dans le catalogue officiel')
    for item in sorted(resources,key=lambda r:r['title']):
        dest=folder/item['title'];url=item.get('url') or ''
        if not url.startswith('https://'):raise ValueError('URL non HTTPS dans le catalogue officiel')
        if dest.exists() and dest.stat().st_size>1024:print('DÉJÀ TÉLÉCHARGÉ:',dest.name);yield dest,url;continue
        size=int(item.get('filesize') or 0)
        if size and size>max_gb*1024**3:raise ValueError('Taille > plafond de téléchargement: '+dest.name)
        print('Téléchargement :',dest.name,flush=True)
        staging=dest.with_suffix('.json.part');written=0
        with urlopen(Request(url,headers={'User-Agent':'Populorum-DECP/1.0'}),timeout=90) as r,staging.open('wb') as w:
            if not r.url.startswith('https://'):raise ValueError('Redirection non HTTPS')
            while c:=r.read(1024*1024):
                written+=len(c)
                if written>max_gb*1024**3:raise ValueError('Fichier trop volumineux '+dest.name)
                w.write(c)
        if not staging.read_bytes()[:100].lstrip().startswith(b'{'):raise ValueError('Fichier sans JSON: '+dest.name)
        staging.replace(dest)
        yield dest,url

def main():
    ap=argparse.ArgumentParser(description=__doc__,formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('source',nargs='?',type=Path,help='Fichier JSON ou dossier avec --all-files')
    ap.add_argument('--all-files',action='store_true',help='Importer tous les decp-AAAA-MM.json d’un dossier')
    ap.add_argument('--download-all',action='store_true',help='Télécharger et importer toutes les ressources mensuelles du catalogue officiel (plusieurs Go)')
    ap.add_argument('--download-dir',type=Path,default=ROOT/'data/research/decp-sources')
    ap.add_argument('--max-gb',type=float,default=2.0,help='Taille maximum PAR fichier téléchargé (défaut 2 Go)')
    ap.add_argument('--db',type=Path,default=DEFAULT_DB)
    ap.add_argument('--source-url',default='',help='URL exacte du fichier officiel, si connue')
    ap.add_argument('--stats',action='store_true')
    opt=ap.parse_args()
    if opt.download_all and opt.source:ap.error('Ne mélangez pas --download-all et source')
    if opt.all_files and (not opt.source or not opt.source.is_dir()):ap.error('--all-files attend un dossier')
    opt.db.parent.mkdir(parents=True,exist_ok=True)
    with sqlite3.connect(opt.db,timeout=45) as db:
        db.execute('PRAGMA busy_timeout=45000')
        db.execute('PRAGMA journal_mode=WAL')
        prior=db.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='metadata'").fetchone()
        if prior:
            version=db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
            if version and version[0]!='populorum-decp-2':
                raise ValueError('Schéma DECP antérieur : reconstruire un index v2 neuf, sans migration implicite.')
        db.executescript(SCHEMA)
        ensure_fts(db)
        ensure_history_schema(db)
        if opt.stats:
            for table in ('imports','contracts','participants','changes','concession_finance'):
                print(table,db.execute('SELECT COUNT(*) FROM '+table).fetchone()[0])
            return
        if opt.download_all:files=download_all(opt.download_dir,opt.max_gb)
        elif opt.all_files:files=((p,'') for p in sorted(opt.source.glob('decp-????-??.json')))
        elif opt.source:files=[(opt.source,'')]
        else:ap.error('Précisez un fichier, --all-files, --download-all ou --stats')
        for path,url in files:import_file(db,path,opt.source_url or url)
        for table in ('imports','contracts','participants','changes'):
            print(table+':',db.execute('SELECT COUNT(*) FROM '+table).fetchone()[0])

if __name__=='__main__':
    try:main()
    except (ValueError,OSError,sqlite3.DatabaseError,UnicodeError,json.JSONDecodeError) as e:sys.exit('ÉCHEC: '+str(e))
