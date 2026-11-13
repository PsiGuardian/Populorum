#!/usr/bin/env python3
"""Audit the local DECP/BOAMP evidence, optionally cross-check the official API.

NO modification of either database. No claims that an absence is proven.

From apps/symfony:
  python3 tools/audit_procurement_concordance.py --offset 0 --limit 25
  python3 tools/audit_procurement_concordance.py --offset 0 --limit 25 --online
  python3 tools/audit_procurement_concordance.py --contract-key <40-hex> --online
"""
from __future__ import annotations
import argparse
import json
from pathlib import Path
import re
import sqlite3
import time
from urllib.parse import urlencode,urlsplit
from urllib.request import Request,urlopen
from reconcile_procurement_boamp import API,API_HOST,classify,terms

ROOT=Path(__file__).resolve().parents[1]
NATIONAL=ROOT/'data/research/public-procurement.sqlite'
SIDECAR=ROOT/'data/research/procurement-auto-concordance.sqlite'


def single_line(value: object, limit: int=300) -> str:
    return str(value if value is not None else 'inconnu').replace('\n',' ').replace('\r',' ')[:limit]


def amount(value: object) -> str:
    if value is None:return 'non renseigné'
    n=int(value)
    return ('-' if n<0 else '')+f'{abs(n)//100:,}'.replace(',',' ')+f',{abs(n)%100:02d} EUR (valeur DECP, pas paiement)'


def contracts(db: sqlite3.Connection, key: str | None, limit: int, offset: int) -> list[dict]:
    base='SELECT contract_key,buyer_id,original_id,object,notice_date,cpv,amount_initial_cents FROM contracts '
    if key:
        result=db.execute(base+'WHERE contract_key=?',(key,))
    else:
        result=db.execute(base+"""WHERE kind='market' AND LENGTH(original_id)>=6
          AND buyer_id GLOB '[0-9]*' AND LENGTH(buyer_id)=14
          ORDER BY notice_date DESC, contract_key ASC LIMIT ? OFFSET ?""",(limit,offset))
    return [dict(row) for row in result]


def known(sidecar: sqlite3.Connection,key: str) -> tuple[dict | None,list[dict]]:
    check=sidecar.execute('SELECT status,checked_at,result_count,message FROM checks WHERE contract_key=?',(key,)).fetchone()
    refs=sidecar.execute("""SELECT idweb,status,basis,title,url,publication_date,notice_kind
                FROM reference_matches WHERE contract_key=?
                ORDER BY status,idweb""",(key,)).fetchall()
    return (dict(check) if check else None,[dict(row) for row in refs])


def known_local(sidecar:sqlite3.Connection,key:str)->tuple[dict|None,list[dict],bool]:
    """Separate national-BOAMP local evidence from prior online API checks."""
    tables={r[0] for r in sidecar.execute(
        "SELECT name FROM sqlite_master WHERE type='table' AND name IN ('local_checks','local_matches')")}
    if 'local_checks' not in tables or 'local_matches' not in tables:
        return None,[],False
    check=sidecar.execute("""SELECT status,scanned_notices,matched_notices,
        truncated,source_revision FROM local_checks WHERE contract_key=?""",(key,)).fetchone()
    refs=sidecar.execute("""SELECT idweb,status,basis,title,url,publication_date,notice_kind
        FROM local_matches WHERE contract_key=? AND active=1
        ORDER BY status,idweb""",(key,)).fetchall()
    return (dict(check) if check else None,[dict(row) for row in refs],True)


def decision_timeline(sidecar:sqlite3.Connection,key:str,limit:int=40)->list[dict]:
    """Read-only audit of versioned matching decisions, no historical guess."""
    table=sidecar.execute("""SELECT 1 FROM sqlite_master
        WHERE type='table' AND name='local_decision_history'""").fetchone()
    if table is None:
        return []
    return [dict(row) for row in sidecar.execute("""
        SELECT event_id,idweb,event_type,classification,algorithm_version,
               algorithm_sha256,contract_sha256,notice_sha,factors_json,
               source_revision,corpus_status,basis,recorded_at
        FROM local_decision_history WHERE contract_key=?
        ORDER BY event_id DESC LIMIT ?""",(key,limit))]


def scan_timeline(sidecar:sqlite3.Connection,key:str,limit:int=12)->list[dict]:
    """Scan history includes indeterminate/zero-candidate observations."""
    exists=sidecar.execute("""SELECT 1 FROM sqlite_master
        WHERE type='table' AND name='local_scan_history'""").fetchone()
    if exists is None:
        return []
    return [dict(row) for row in sidecar.execute("""
        SELECT scan_id,result,algorithm_version,algorithm_sha256,
               contract_sha256,source_revision,corpus_status,scanned_notices,
               matched_notices,truncated,match_signature,recorded_at
        FROM local_scan_history WHERE contract_key=?
        ORDER BY scan_id DESC LIMIT ?""",(key,limit))]


def official_query(buyer: str,field: str,word: str) -> tuple[int,list[dict]]:
    if not re.fullmatch(r'[0-9]{14}',buyer):
        raise ValueError('SIRET invalide')
    if field not in ('objet','donnees') or not 3<=len(word)<=70 or not re.fullmatch(r'[A-Za-z0-9_-]+',word):
        raise ValueError('Critère non autorisé')
    where="search(donnees, '"+buyer+"') AND search("+field+", '"+word+"')"
    url=API+'?'+urlencode({'where':where,'select':'idweb,objet,nomacheteur,dateparution,url_avis,donnees','limit':25})
    with urlopen(Request(url,headers={'Accept':'application/json','User-Agent':'Populorum-BOAMP-audit/1.0'}),timeout=25) as answer:
        if urlsplit(answer.url).hostname!=API_HOST or urlsplit(answer.url).scheme!='https':
            raise ValueError('Redirection hors domaine officiel')
        raw=answer.read(5_000_001)
    if len(raw)>5_000_000:raise ValueError('Réponse trop volumineuse')
    body=json.loads(raw)
    if not isinstance(body,dict) or not isinstance(body.get('results'),list):
        raise ValueError('Réponse API inattendue')
    return (int(body.get('total_count',len(body['results']))),body['results'])


def second_look(contract: dict) -> list[str]:
    buyer=str(contract['buyer_id'] or '')
    ref=str(contract['original_id'] or '').strip()
    tokens=list(dict.fromkeys(x for x in terms(str(contract['object'])) if len(x)>=5 and not x.isdigit()))
    criteria=[]
    if re.fullmatch(r'[A-Za-z0-9_-]{6,70}',ref):
        criteria.append(('Référence DECP dans avis', 'donnees',ref))
    if tokens:criteria.append(('Autre mot objet','objet',tokens[0]))
    if len(tokens)>1:criteria.append(('Dernier mot objet','objet',tokens[-1]))
    output=[]
    seen=set()
    for label,field,word in criteria[:3]:
        try:
            total,rows=official_query(buyer,field,word)
            output.append('  Contre-recherche '+label+': '+str(total)+' avis au total, '+str(len(rows))+' inspectés')
            for row in rows:
                webid=str(row.get('idweb') or '')
                url=str(row.get('url_avis') or '')
                p=urlsplit(url)
                if webid in seen or p.scheme!='https' or p.hostname not in ('www.boamp.fr','boamp.fr'):continue
                seen.add(webid)
                match=classify(contract,row)
                status=match['status'] if match else 'non retenu automatiquement (lien non exclu)'
                output.append('    '+single_line(webid)+' | '+status+' | '+single_line(row.get('objet'),130)+' | '+url)
            if total>len(rows):
                output.append('    ATTENTION : résultats tronqués ; aucune exclusion possible.')
        except (OSError,ValueError,TypeError,KeyError) as e:
            output.append('  Recherche indisponible : '+single_line(e,200))
        time.sleep(1.1)
    return output


def run(db: sqlite3.Connection,sidecar: sqlite3.Connection,rows: list[dict],online: bool,local: bool=False,history: bool=False) -> str:
    output=['AUDIT DOCUMENTAIRE BOAMP - DECP','Toute absence de correspondance signifie INDETERMINE, jamais aucun lien.','']
    checks=potential=confirmed=0
    local_checks=local_potential=local_confirmed=local_conflicts=local_phase=0
    for i,row in enumerate(rows,1):
        check,refs=known(sidecar,row['contract_key'])
        checks+=bool(check)
        potential+=sum(x['status']=='candidate' for x in refs)
        confirmed+=sum(x['status']=='procedure_ids' for x in refs)
        output.extend([
          '['+str(i)+'] '+single_line(row['object']),
          '  Cle contrat '+row['contract_key'],
          '  Acheteur '+single_line(row['buyer_id'])+' | reference '+single_line(row['original_id'])+
            ' | notification '+single_line(row['notice_date'])+' | CPV '+single_line(row['cpv']),
          '  Montant '+amount(row['amount_initial_cents']),
          '  Recherche '+(single_line(check['status'])+' le '+single_line(check['checked_at']) if check else 'NON EXECUTEE')+
            ' ; '+str(len(refs))+' avis sauvegardes',
        ])
        for r in refs:
            output.append('  '+r['status'].upper()+' BOAMP '+single_line(r['idweb'])+' | '+single_line(r['title'])+
                          ' | '+single_line(r['publication_date'])+' | '+single_line(r['url']))
            output.append('    Motif : '+single_line(r['basis']))
        if not refs:
            output.append('  INDETERMINE : aucune preuve de NON-correspondance.')
        if local:
            local_check,local_refs,available=known_local(sidecar,row['contract_key'])
            output.append('  Référentiel BOAMP local (indépendant des recherches API):')
            if not available:
                output.append('    Index local NON INSTALLE ; couverture INDETERMINEE.')
            elif not local_check:
                output.append('    Contrat NON EXAMINE localement ; couverture INDETERMINEE.')
            else:
                local_checks+=1
                local_potential+=sum(x['status']=='candidate' for x in local_refs)
                local_confirmed+=sum(x['status']=='procedure_ids' for x in local_refs)
                local_conflicts+=sum(x['status']=='object_conflict' for x in local_refs)
                local_phase+=sum(x['status']=='phase_conflict' for x in local_refs)
                output.append('    Couverture '+single_line(local_check['status'])+
                    ' | avis inspectés '+str(local_check['scanned_notices'])+
                    ' | plafond atteint '+str(bool(local_check['truncated']))+
                    ' | import '+single_line(local_check['source_revision']))
                for r in local_refs:
                    output.append('    '+r['status'].upper()+' BOAMP '+single_line(r['idweb'])+
                        ' | '+single_line(r['title'])+' | '+single_line(r['url']))
                    output.append('      Motif : '+single_line(r['basis']))
                    if r['status']=='object_conflict':
                        output.append('      AVERTISSEMENT : ancien candidat API éventuellement reclassé ; '
                                      'conflit lexical indicatif, et non absence prouvée de lien.')
                    if r['status']=='phase_conflict':
                        output.append('      AVERTISSEMENT : maîtrise d’œuvre distincte des travaux DECP '
                                      '(CPV 45) ; même opération possible, lien direct non attesté.')
                if not local_refs:
                    output.append('    INDETERMINE : aucun avis lié dans la couverture disponible.')
                if local_check['status']=='partial':
                    output.append('    ATTENTION : corpus partiel ; aucune absence de lien prouvée.')
        if history:
            output.append('  HISTORIQUE DES CONTROLES LOCAUX (même sans candidat):')
            scans=scan_timeline(sidecar,row['contract_key'])
            if not scans:
                output.append('    Aucun contrôle versionné ; anciens contrôles non datables par version.')
            for check_event in scans:
                output.append('    CONTROLE #'+str(check_event['scan_id'])+
                    ' | '+single_line(check_event['result'])+
                    ' | '+str(check_event['scanned_notices'])+' avis inspectés'+
                    ' | '+str(check_event['matched_notices'])+' pistes'+
                    ' | corpus '+single_line(check_event['corpus_status'])+
                    ' | révision '+single_line(check_event['source_revision'])+
                    ' | règles '+single_line(check_event['algorithm_version'])+
                    ' | empreinte '+check_event['algorithm_sha256'][:12]+
                    ' | observé le '+single_line(check_event['recorded_at']))
                if not check_event['matched_notices']:
                    output.append('      INDETERMINE : 0 candidat observé ne prouve PAS une absence de lien.')
            output.append('  HISTORIQUE DES DECISIONS DE L’ALGORITHME (événements les plus récents):')
            timeline=decision_timeline(sidecar,row['contract_key'])
            if not timeline:
                output.append('    Aucun événement versionné disponible (anciens résultats non réinterprétés).')
            for event in timeline:
                output.append('    #'+str(event['event_id'])+' | BOAMP '+event['idweb']+
                    ' | '+event['event_type']+' | '+event['classification']+
                    ' | algorithme '+single_line(event['algorithm_version'])+
                    ' | empreinte règles '+single_line(event['algorithm_sha256'][:12])+
                    ' | source '+single_line(event['notice_sha'][:12])+
                    ' | couverture '+single_line(event['corpus_status'])+
                    ' | date de journalisation '+single_line(event['recorded_at']))
                if event['event_type']=='legacy_snapshot':
                    output.append('      Historique antérieur NON RECONSTITUABLE : statut récupéré, '
                                  'version et calcul inconnus.')
                else:
                    output.append('      Facteurs : '+single_line(event['factors_json'],600))
                output.append('      Justification : '+single_line(event['basis'],300))
        if online:
            output.append('  Contre-recherches officielles, NON exhaustives:')
            output.extend(second_look(row))
        output.append('')
    output.extend(['BILAN : '+str(len(rows))+' contrats ; '+str(checks)+' controles disponibles ; '+
                   str(confirmed)+' concordances procedures ; '+str(potential)+
                   ' candidats provenant des recherches API historiques (non reclassés localement).',
                   'Aucun montant BOAMP ne prouve un paiement ni ne valide automatiquement un lot.'])
    if local:
        output.append('LOCAL : '+str(local_checks)+' contrats examinés ; '+
             str(local_confirmed)+' concordances de procédure ; '+
             str(local_potential)+' candidats ; '+
             str(local_conflicts)+' avis à conflit d’objet ; '+
             str(local_phase)+' avis à phase distincte ; couverture partielle possible.')
    return '\n'.join(output)+'\n'


def main() -> None:
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--offset',type=int,default=0)
    p.add_argument('--limit',type=int,default=25)
    p.add_argument('--contract-key')
    p.add_argument('--decp',type=Path,default=NATIONAL)
    p.add_argument('--sidecar',type=Path,default=SIDECAR)
    p.add_argument('--online',action='store_true',help='Contre-recherches DILA bornées, sans écriture')
    p.add_argument('--local',action='store_true',help='Inclure les concordances BOAMP locales et leur couverture')
    p.add_argument('--history',action='store_true',help='Afficher le journal des décisions versionnées (avec --local)')
    p.add_argument('--output',type=Path,help='Fichier de rapport local UTF-8')
    args=p.parse_args()
    if args.offset<0 or not 1<=args.limit<=50:p.error('offset >= 0 et limite de 1 à 50')
    if args.history and not args.local:p.error('--history nécessite --local')
    if args.contract_key and not re.fullmatch(r'[a-f0-9]{40}',args.contract_key):p.error('contract-key invalide')
    if not args.decp.is_file() or not args.sidecar.is_file():p.error('Fichiers DECP et concordance absents')
    with sqlite3.connect('file:'+str(args.decp.resolve())+'?mode=ro',uri=True) as db, \
         sqlite3.connect('file:'+str(args.sidecar.resolve())+'?mode=ro',uri=True) as evidence:
        db.row_factory=evidence.row_factory=sqlite3.Row
        report=run(db,evidence,contracts(db,args.contract_key,args.limit,args.offset),args.online,args.local,args.history)
    if args.output:
        args.output.parent.mkdir(parents=True,exist_ok=True)
        args.output.write_text(report,encoding='utf-8')
        print('Rapport écrit :',args.output)
    else:
        print(report)


if __name__=='__main__':
    main()
