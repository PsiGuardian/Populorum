#!/usr/bin/env python3
"""Audit the morphological behaviour of BOAMP title matching on ALL INSEE communes.

This script does not declare or invent municipality demonyms and does not
modify any database. It checks the entire locally installed COG 2026 catalog
for ambiguities created by singular/plural token normalization.

From apps/symfony:
  python3 tools/audit_boamp_commune_morphology.py
  python3 tools/audit_boamp_commune_morphology.py --report var/cog-report.txt --rows var/cog-communes.tsv
"""
from __future__ import annotations
import argparse
from collections import defaultdict
import csv
from pathlib import Path
import re
import sqlite3
import unicodedata


ROOT=Path(__file__).resolve().parents[1]
GEOGRAPHY=ROOT/'data/geography/communes-2026.sqlite'
DEFAULT_REPORT=ROOT/'var/boamp-cog-2026-report.txt'
DEFAULT_ROWS=ROOT/'var/boamp-cog-2026-communes.tsv'


def clean(value:str)->str:
    raw=unicodedata.normalize('NFKD',value.casefold().replace('œ','oe').replace('æ','ae'))
    raw=''.join(c for c in raw if not unicodedata.combining(c))
    return ' '.join(re.findall(r'[a-z0-9]+',raw))


def geographic_tokens(name:str)->tuple[str,...]:
    """Keep EVERY geographic word, including short official names.

    Contract-title terms discard words <4 chars and several stop words.
    Applying that filter to place names turns Bey, Boz, Gap and Le Pin
    into the same empty set; it must never be used for geography.
    """
    return tuple(clean(name).split())


def plural_heuristic(word:str)->str:
    """Potential plural variant ONLY; never a validated INSEE alias.

    This mirrors the bounded rule of the BOAMP candidate-title engine.
    As a heuristic it can also transform proper names (e.g. Thiers)
    incorrectly; that is why its result is reported, not accepted as an ID.
    """
    if len(word)>=6 and word.endswith('s') and not word.endswith(('ss','us','is')):
        return word[:-1]
    return word


def keys(name:str)->tuple[str,str]:
    tokens=geographic_tokens(name)
    if not tokens:
        raise ValueError('Clé géographique vide pour : '+name)
    strict=' '.join(sorted(tokens))
    plural=' '.join(sorted(plural_heuristic(t) for t in tokens))
    if not strict or not plural:
        raise ValueError('Clé géographique vide pour : '+name)
    return strict,plural


def inspect(path:Path,minimum:int=34000)->tuple[list[dict],dict]:
    if not path.is_file():
        raise ValueError('Référentiel INSEE absent : installer communes-2026.sqlite '
                         '(php bin/console app:geography:install --env=demo)')
    uri='file:'+str(path.resolve())+'?mode=ro'
    with sqlite3.connect(uri,uri=True) as db:
        db.row_factory=sqlite3.Row
        tables={r[0] for r in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}
        if not {'metadata','communes'}.issubset(tables):
            raise ValueError('Référentiel INSEE incomplet : tables communes/metadata manquantes')
        vintage=db.execute("SELECT value FROM metadata WHERE key='vintage'").fetchone()
        if vintage is None or vintage[0]!='2026':
            raise ValueError('Millésime COG 2026 requis (aucun mélange de millésimes)')
        records=db.execute('SELECT code,name FROM communes ORDER BY code').fetchall()
    if len(records)<minimum:
        raise ValueError(f'COG insuffisant : {len(records)} communes < {minimum}')

    canonical=defaultdict(set)
    strict_groups=defaultdict(set)
    plural_groups=defaultdict(set)
    all_rows=[]
    for r in records:
        code=str(r['code'])
        name=str(r['name'])
        if not re.fullmatch(r'(?:[0-9]{5}|2[AB][0-9]{3})',code):
            raise ValueError('Code INSEE invalide : '+code)
        if not name.strip():
            raise ValueError('Libellé de commune vide : '+code)
        full=clean(name)
        strict,plural=keys(name)
        tokens=set(geographic_tokens(name))
        inflected={plural_heuristic(t) for t in geographic_tokens(name)}
        canonical[full].add(code)
        strict_groups[strict].add(code)
        plural_groups[plural].add(code)
        all_rows.append({
            'code_insee':code,'commune_officielle':name,
            'libelle_normalise':full,'cle_lexicale':strict,
            'cle_pluriel':plural,
            'termes_modifies':' '.join(sorted(tokens-inflected)),
            'termes_crees':' '.join(sorted(inflected-tokens)),
        })

    distinct=len({r['code_insee'] for r in all_rows})
    if distinct!=len(all_rows):
        raise ValueError('Codes INSEE dupliqués')

    aliases_ambiguous=0
    new_plural_ambiguities=0
    plural_impacted=0
    exact_name_collisions=0
    for row in all_rows:
        same_name=canonical[row['libelle_normalise']]
        same_terms=strict_groups[row['cle_lexicale']]
        same_plural=plural_groups[row['cle_pluriel']]
        row['homonymes_nom_officiel']=len(same_name)
        row['homonymes_cle_lexicale']=len(same_terms)
        row['homonymes_cle_pluriel']=len(same_plural)
        row['autres_codes_cle_lexicale']=' '.join(sorted(same_terms-{row['code_insee']}))
        row['autres_codes_cle_pluriel']=' '.join(sorted(same_plural-{row['code_insee']}))
        row['ambiguite_introduite_par_pluriel']=int(len(same_terms)==1 and len(same_plural)>1)
        if len(same_name)>1:exact_name_collisions+=1
        if len(same_terms)>1:aliases_ambiguous+=1
        if row['termes_modifies'] or row['termes_crees']:plural_impacted+=1
        if row['ambiguite_introduite_par_pluriel']:new_plural_ambiguities+=1

    summary={
        'communes':len(all_rows),
        'codes_uniques':distinct,
        'libelles_officiels_avec_homonymes':exact_name_collisions,
        'cles_lexicales_ambigues':aliases_ambiguous,
        'cles_vides':sum(not row['cle_lexicale'] for row in all_rows),
        'libelles_affectes_par_normalisation':plural_impacted,
        'communes_ambigues_supplementaires_apres_pluriels':new_plural_ambiguities,
        'annee':2026,
    }
    return all_rows,summary


def write_results(rows:list[dict],summary:dict,report:Path,table:Path)->None:
    report.parent.mkdir(parents=True,exist_ok=True)
    table.parent.mkdir(parents=True,exist_ok=True)
    with table.open('w',encoding='utf-8',newline='') as output:
        columns=['code_insee','commune_officielle','libelle_normalise',
                 'cle_lexicale','cle_pluriel','termes_modifies','termes_crees',
                 'homonymes_nom_officiel','homonymes_cle_lexicale',
                 'autres_codes_cle_lexicale','homonymes_cle_pluriel',
                 'autres_codes_cle_pluriel','ambiguite_introduite_par_pluriel']
        writer=csv.DictWriter(output,fieldnames=columns,delimiter='\t')
        writer.writeheader()
        writer.writerows(rows)
    with report.open('w',encoding='utf-8') as output:
        output.write('AUDIT GEOGRAPHIQUE BOAMP / INSEE COG 2026\n')
        output.write('Source : https://www.insee.fr/fr/information/8740222\n')
        output.write('Périmètre : toutes les communes du millésime 2026 (TYPECOM=COM).\n')
        output.write('AUCUNE association contractuelle ne peut être prouvée par une forme de nom.\n')
        output.write('CORRECTION : tous les mots des communes (y compris <4 lettres) sont préservés.\n')
        output.write('L’analyse des pluriels est une HEURISTIQUE : ne valide aucun gentilé ni alias.\n')
        output.write('Les compteurs d’homonymie désignent des COMMUNES touchées, pas des groupes.\n')
        output.write('Les gentilés ne figurent pas dans le COG : aucun gentilé artificiel généré.\n')
        output.write('Normands/Normandie désigne une région et un adjectif, pas un pluriel de commune.\n\n')
        for key,val in summary.items():
            output.write(f'{key} : {val}\n')
        output.write('\nVARIANTES POTENTIELLES DUES AU PLURIEL (max. 20 exemples)\n')
        variants=[r for r in rows if r['ambiguite_introduite_par_pluriel']]
        variants.sort(key=lambda r:(r['cle_pluriel'],r['code_insee']))
        for r in variants[:20]:
            output.write(f"{r['code_insee']} | {r['commune_officielle']} | "
                         f"autres codes possibles (NON VALIDÉS) : "
                         f"{' '.join(r['autres_codes_cle_pluriel'].split()[:12])}\n")
        output.write('\nHOMONYMIES OU INVERSIONS DE MOTS DÉJÀ PRÉSENTES (max. 20 exemples)\n')
        examples=[r for r in rows if r['homonymes_cle_lexicale']>1]
        examples.sort(key=lambda r:(r['cle_lexicale'],r['code_insee']))
        for r in examples[:20]:
            output.write(f"{r['code_insee']} | {r['commune_officielle']} | "
                         f"autres codes : {' '.join(r['autres_codes_cle_lexicale'].split()[:12])}\n")
        output.write('\nLes variantes lexicales ambiguës ne doivent jamais devenir des identifiants géographiques.\n')
        output.write('Utiliser le code INSEE lorsqu’il est explicitement déclaré ; sinon garder une piste incertaine.\n')
        output.write('Détail exhaustif (une ligne par commune) : '+str(table)+'\n')


def main()->None:
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--geography',type=Path,default=GEOGRAPHY)
    parser.add_argument('--report',type=Path,default=DEFAULT_REPORT)
    parser.add_argument('--rows',type=Path,default=DEFAULT_ROWS)
    parser.add_argument('--min-communes',type=int,default=34000)
    args=parser.parse_args()
    if args.min_communes<1:
        parser.error('--min-communes doit être positif')
    if args.report.resolve()==args.rows.resolve():
        parser.error('Rapport et tableau doivent être deux fichiers distincts')
    if args.report.resolve()==args.geography.resolve() or args.rows.resolve()==args.geography.resolve():
        parser.error('Interdiction de réécrire la base INSEE')
    try:
        rows,summary=inspect(args.geography,args.min_communes)
        write_results(rows,summary,args.report,args.rows)
    except (sqlite3.Error,ValueError,OSError) as error:
        parser.exit(1,'ERREUR : '+str(error)+'\n')
    print('COG 2026 contrôlé :',summary['communes'],'communes ;',
          summary['cles_lexicales_ambigues'],'communes partageant une clé stricte ;',
          summary['communes_ambigues_supplementaires_apres_pluriels'],
          'communes devenant ambiguës par la règle des pluriels ;',
          summary['cles_vides'],'clés vides.')
    print('Rapport :',args.report)
    print('Détail de chaque commune :',args.rows)
    print('Bases INSEE, BOAMP et DECP non modifiées.')


if __name__=='__main__':
    main()
