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


def run(db: sqlite3.Connection,sidecar: sqlite3.Connection,rows: list[dict],online: bool) -> str:
    output=['AUDIT DOCUMENTAIRE BOAMP - DECP','Toute absence de correspondance signifie INDETERMINE, jamais aucun lien.','']
    checks=potential=confirmed=0
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
        if online:
            output.append('  Contre-recherches officielles, NON exhaustives:')
            output.extend(second_look(row))
        output.append('')
    output.extend(['BILAN : '+str(len(rows))+' contrats ; '+str(checks)+' controles disponibles ; '+
                   str(confirmed)+' concordances procedures ; '+str(potential)+' candidats.',
                   'Aucun montant BOAMP ne prouve un paiement ni ne valide automatiquement un lot.'])
    return '\n'.join(output)+'\n'


def main() -> None:
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--offset',type=int,default=0)
    p.add_argument('--limit',type=int,default=25)
    p.add_argument('--contract-key')
    p.add_argument('--decp',type=Path,default=NATIONAL)
    p.add_argument('--sidecar',type=Path,default=SIDECAR)
    p.add_argument('--online',action='store_true',help='Contre-recherches DILA bornées, sans écriture')
    p.add_argument('--output',type=Path,help='Fichier de rapport local UTF-8')
    args=p.parse_args()
    if args.offset<0 or not 1<=args.limit<=50:p.error('offset >= 0 et limite de 1 à 50')
    if args.contract_key and not re.fullmatch(r'[a-f0-9]{40}',args.contract_key):p.error('contract-key invalide')
    if not args.decp.is_file() or not args.sidecar.is_file():p.error('Fichiers DECP et concordance absents')
    with sqlite3.connect('file:'+str(args.decp.resolve())+'?mode=ro',uri=True) as db, \
         sqlite3.connect('file:'+str(args.sidecar.resolve())+'?mode=ro',uri=True) as evidence:
        db.row_factory=evidence.row_factory=sqlite3.Row
        report=run(db,evidence,contracts(db,args.contract_key,args.limit,args.offset),args.online)
    if args.output:
        args.output.parent.mkdir(parents=True,exist_ok=True)
        args.output.write_text(report,encoding='utf-8')
        print('Rapport écrit :',args.output)
    else:
        print(report)


if __name__=='__main__':
    main()
