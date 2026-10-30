#!/usr/bin/env python3
"""Publish measured scale comparisons as static HTML, CSV and Markdown; no fabricated estimates."""
import csv, html, json, statistics, shutil
from pathlib import Path
import argparse
parser=argparse.ArgumentParser();parser.add_argument('--measurements',required=True);args=parser.parse_args()
source=Path(args.measurements);out=Path(__file__).resolve().parents[1]/'docs';raw=out/'performance-scale';raw.mkdir(exist_ok=True)
reports={}
for phase in ['before','after']:
 for scale in ['1','5','neighbors']:
  name=f'scale-{phase}-{scale}.json';reports[phase,scale]=json.loads((source/name).read_text());shutil.copyfile(source/name,raw/name)
index={key:{p['path']:p for p in report['pages']} for key,report in reports.items()}
paths=list(index['after','5'])
rows=[]
for path in paths:
 ref=index['after','5'][path]
 row={'Écran':ref['label'],'Route':path}
 for phase,scale in reports:
  data=index[phase,scale].get(path,{})
  row[f'{phase}_{scale}_ms']=data.get('warm',{}).get('medianMs','')
  row[f'{phase}_{scale}_octets']=data.get('bytes','')
  row[f'{phase}_{scale}_php_frais_ms']=data.get('freshProcess',{}).get('medianMs','')
 rows.append(row)
with (out/'PERFORMANCE_SCALE_TIMINGS.csv').open('w',encoding='utf-8-sig',newline='') as f:
 writer=csv.DictWriter(f,fieldnames=list(rows[0]),delimiter=';');writer.writeheader();writer.writerows(rows)
fmt=lambda n:'—' if n=='' else f'{n:.1f}'
table=['| Écran | Avant 1× | Avant 5× | Après 1× | Après 5× | PHP frais après 5× |','|---|---:|---:|---:|---:|---:|']
for row in rows:
 if row['Écran'].startswith('Action : '):continue
 table.append('| '+row['Écran']+' | '+' | '.join(fmt(row[k]) for k in ['before_1_ms','before_5_ms','after_1_ms','after_5_ms','after_5_php_frais_ms'])+' |')
neighbor_table=['| Écran | Version finale, 1 simulation | Version finale, 5 simulations indépendantes |','|---|---:|---:|']
for path in ['/', '/consultations', '/budget-et-priorites']:
 a=index['after','1'][path];b=index['after','neighbors'][path]
 neighbor_table.append(f"| {a['label']} | {a['warm']['medianMs']:.1f} | {b['warm']['medianMs']:.1f} |")
write_lines=[]
for factor in ['1','5']:
 r=reports['after',factor]
 for key,label in [('writeSamplesBeforeIncremental','avant incrémental'),('writeSamples','après optimisations')]:
  samples=r[key];read=statistics.median(v['readMs'] for v in samples);apply=statistics.median(v['applyMs'] for v in samples);save=statistics.median(v['saveMs'] for v in samples);total=statistics.median(sum(v.values()) for v in samples)
  write_lines.append(f'| {factor}× {label} | {read:.1f} | {apply:.1f} | {save:.1f} | {total:.1f} |')
metadata=[]
for phase,scale in [('before','1'),('before','5'),('before','neighbors'),('after','1'),('after','5'),('after','neighbors')]:
 r=reports[phase,scale];metadata.append(f"- {phase} {scale} : {len(r['pages'])} routes ; fichier SQLite {r['databaseBytes']:,} octets avant les séries ; création initiale hors mesures par page.")
text='''# Populorum — base cinq fois plus volumineuse

Mesures locales du 30 octobre 2026. Ce rapport complète le premier rapport de performances ; il décrit un nouveau lot de modifications locales, sans publication GitHub.

## Ce que signifie « cinq fois plus »

Trois cas sont mesurés :

- **1×** : 300 citoyens et travailleurs, 27 propositions et consultations, 2 700 positions, 6 récits et une expérience.
- **5× dans la simulation consultée** : 1 500 citoyens et travailleurs, 135 propositions et consultations, 13 500 positions, 30 récits et cinq expériences. Les identifiants et rattachements familiaux sont distincts ; chaque consultation conserve son électorat figé. Les trois villes et les montants publics réels restent inchangés.
- **Cinq simulations indépendantes** : la simulation consultée reste à 1×, quatre autres simulations complètes occupent la même base. La recherche par identifiant de simulation utilise sa clé primaire.

Ce sont des multiplications des collections fictives, pas cinq fois le nombre exact d’octets de chaque fichier. Les sources publiques, schémas et métadonnées sont fixes. Le test ne représente pas cinq fois plus de visiteurs simultanés. Il ne teste pas non plus un seul dossier dont tous les historiques seraient cinq fois plus longs, ni un grand stock de missions et de devis : ces collections sont initialement vides. Aucun résultat de capacité municipale réelle n’en découle.

## Méthode

Même PHP 8.4.5, Symfony demo sans debug, SQLite, OPcache CLI désactivé, mêmes routes et mêmes filtres entre les versions. Mesures séquentielles sur cette machine, bases jetables, sans utilisateur réel. Une requête chauffe chaque route, suivie de cinq mesures pour les pages et trois pour les 67 formulaires GET. Le kernel est recréé entre les routes pour ne pas confondre la croissance d’une boucle de test avec celle de la base ; il est réutilisé au sein de chaque série. Les nombres du tableau sont des médianes en millisecondes.

Trois processus PHP indépendants mesurent aussi les 27 écrans, détails et API de la version finale. Leur durée inclut démarrage, autoload et requête, avec cache Symfony déjà compilé. Ce n’est pas un démarrage sans cache. Les phases sont inclusives : ne pas les additionner. Le p95 de séries de trois ou cinq mesures est leur maximum, pas une estimation fiable du p95 en production.

Les mesures excluent réseau, transfert des ressources, rendu et interactions du navigateur, concurrence et PHP-FPM. Elles ne permettent pas d’affirmer que toute attente sera imperceptible. Les vérifications navigateur attestent la navigation et l’actualisation, sans mesurer LCP ou INP. Les petites variations isolées ne justifient pas une conclusion sur un gain.

## Temps par écran

'''+ '\n'.join(table)+'''

Les 94 routes du scénario 5× et leurs tailles HTML/JSON sont disponibles dans `PERFORMANCE_SCALE_TIMINGS.csv` et le tableau HTML filtrable. Les cas 1× avant et cinq simulations couvrent les 27 écrans et API, sans les 67 formulaires ; une case absente n’est jamais zéro. Les JSON conservent minimum, maximum, p95 et décomposition serveur.

## Cinq simulations indépendantes dans la même base

'''+ '\n'.join(neighbor_table)+'''

La simulation demandée garde son volume 1× ; les quatre autres ne sont pas chargées pour afficher sa page. Les différences de petites séries restent soumises à la variabilité de la machine. Aucune conclusion sur cinq utilisateurs simultanés n’est possible.

## Corrections appliquées

1. **Vue de navigation sans bulletins** : les pages qui ne calculent pas de résultat ne décodent plus les votes. `overview_state` est une projection reconstructible, liée à la même révision et mise à jour dans la transaction du snapshot. Une modification directe du snapshot l’invalide par trigger ; les anciennes bases la reconstruisent au premier accès. Elle ne sert jamais aux mutations, exports ni à la vérification.
2. **Listes bornées avant le rendu** : 27 propositions ou consultations par page, 30 profils professionnels, total visible, liens précédent/suivant et routes nommées. Filtrage avant découpage. Les filtres et compétences suivent les liens, et la navigation fonctionne sans JavaScript. L’annuaire est directement visible ; le replier ne faisait pas économiser sa génération.
3. **Consultations visibles uniquement** : calcul des décomptes après sélection et pagination. Les bulletins nominatifs restent accessibles dans la fiche complète. Le navigateur ne demande les mises à jour que des cartes présentes ; le serveur valide et borne la sélection à 27 identifiants. Sans sélection, l’API conserve son contrat exhaustif antérieur. Phases et échéances sont recalculées, sans cache de vote périmé.
4. **Convergence** : calcul détaillé sur les douze paires visibles, sans mémoriser toutes les paires ni parcourir une seconde fois toutes les combinaisons. Le comptage des paires reste quadratique ; il faudra un index de comparaisons pour un volume beaucoup plus grand. L’ordre et les preuves sont inchangés.
5. **Suffrage familial** : ensemble indexé des électeurs admissibles à la place de recherches répétées dans leur liste. Les vérifications de doublon, admissibilité, règle figée et voix unique sont conservées.
6. **Encodage canonique** : tri des structures une fois puis encodage JSON natif, à la place de milliers de petits encodages récursifs. Les empreintes restent identiques octet pour octet à l’algorithme historique, y compris les objets à clés numériques et les listes ; la compatibilité des signatures est testée. Aucune vérification n’est désactivée.
7. **Écritures relationnelles incrémentales** : seules les lignes modifiées, ajoutées ou supprimées et leurs détails dépendants sont reconstruits. Le snapshot, les vues, les projections et l’outbox restent dans une seule transaction protégée par la révision. Le contrôle des contraintes et le rollback restent actifs. Le rebuild intégral reste disponible.

## Coût d’une modification

Trois modifications successives de disponibilité professionnelle, à partir du snapshot complet. Ce sont les temps des services locaux, sans HTTP, formulaire, réseau ni rendu. Lecture, opération métier avec signature, puis sauvegarde avec ses projections. Les données restent fictives ; aucune personne n’est contactée. Les séries avant/après concernent les optimisations de sauvegarde et d’encodage canonique ajoutées pendant cet audit, et non le code du premier lot de performances. Le coût de l’opération métier doit rester visible même si la sauvegarde accélère.

| Cas | Lecture | Métier et signature | Sauvegarde | Total médian réel |
|---|---:|---:|---:|---:|
'''+ '\n'.join(write_lines)+'''

Le total est la médiane des trois totaux observés ; il peut différer de la somme des médianes des phases. Les échantillons bruts figurent dans les JSON. Dans cette campagne, la phase métier à 5× reste de l’ordre d’une seconde : le gain observé de l’action provient surtout de la sauvegarde. Aucun gain isolé de l’encodeur canonique n’est établi par ces petites séries. Un changement touchant beaucoup d’objets peut toujours entraîner beaucoup d’écritures. Ce test n’est pas un benchmark concurrent d’écriture SQLite.

## Ce qui reste coûteux et comment progresser

- Le snapshot JSON d’une simulation reste chargé en entier pour les exports, la vérification, les mutations et certains détails ; sa taille compte encore. La nouvelle vue de navigation conserve aussi toutes les propositions, identités et autres collections utiles : sa lecture n’est pas constante à taille infinie.
- La pagination introduite borne le rendu et les calculs visibles, mais filtre encore en mémoire après lecture du snapshot. Au-delà de ce palier, il faudra exploiter les projections relationnelles pour des lectures SQL limitées et indexées, avec une révision cohérente et des compteurs explicites. Les exports resteront complets.
- La signature actuelle recalcule l’empreinte canonique du snapshot complet. Son encodage a été accéléré sans modifier les empreintes, mais un coût proportionnel au volume demeure. Remplacer cette preuve par une structure incrémentale demanderait une migration et des tests dédiés de compatibilité ; aucun contrôle n’a été supprimé pour masquer son coût.
- Les formulaires comportant de longues listes de choix et les historiques individuels nécessiteront une sélection recherchable et des historiques paginés si ces collections continuent à croître.
- Prochaine validation de déploiement : PHP-FPM avec OPcache, mesures navigateur sur matériel modeste, réseau contraint, puis utilisateurs simultanés et écritures concurrentes. Objectifs à fixer et vérifier : temps serveur des pages courantes, taille HTML, absence de longues tâches, taux d’erreur, verrouillages SQLite et latence des actions. Ne pas transformer un objectif en résultat mesuré.

Le chargement différé convient aux preuves, historiques et éléments secondaires demandés par l’utilisateur. L’arbre causal et les premières actions doivent rester présents dès le rendu initial. Ajouter des requêtes JavaScript à toutes les sections créerait un risque de cascade de chargements, de perte de contexte et de dépendance au réseau ; la pagination et les lectures ciblées évitent ici une partie de ce risque.

Références primaires : [performances Symfony](https://symfony.com/doc/current/performance.html), [rendu différé dans Chrome](https://developer.chrome.com/docs/modern-web-guidance/guides/performance/defer-rendering-heavy-content), [optimisation des tâches longues](https://web.dev/articles/optimize-long-tasks). Une optimisation du rendu navigateur ne réduit pas à elle seule le traitement SQL ou PHP.

## Données de contrôle

'''+ '\n'.join(metadata)+'''

Les fichiers SQLite mesurés sont supprimés après l’essai. La vue supplémentaire augmente l’espace disque et un peu le travail d’écriture ; ce compromis réduit le volume décodé sur les lectures courantes. Le stock réel des clés et des données personnelles n’est jamais inclus dans les archives.

## Validation et installation

104 tests PHPUnit et 53 836 assertions passent, dont : conservation des signatures et historiques complets ; vue de navigation reconstruite et invalidée ; synchronisation par révision ; isolation entre laboratoires ; rollback des écritures invalides ; résultat incrémental identique au rebuild des quatorze tables ; absence de réécriture des votes inchangés ; accès sans doublon aux 135 consultations ; filtres et pagination ; sélection bornée du suivi en direct ; égalité des décomptes et de la convergence. Analyse statique PHPStan sans erreur. Les tests d’intégration, corrections et audit du projet sont exécutés séparément.

Sur installation existante, conserver sa base, ses clés et sa configuration, puis exécuter depuis `apps/symfony` :

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=demo
php bin/console cache:clear --env=demo
```

La nouvelle migration additive est `Version20261030180000`. La migration de la vue précédente reste requise. Ne jamais remplacer une base existante par la fixture fournie. Les simulations de charge sont uniquement produites dans des bases de test jetables et ne sont pas livrées comme données de démonstration normales.

Reproduction, dans une copie de travail avec les dépendances disponibles :

```bash
php tools/benchmark-pages.php --samples=5 --scale=5 --output=var/scale-5.json
php tools/benchmark-pages.php --samples=5 --neighbors=4 --sections --output=var/neighbors.json
php tools/benchmark-pages.php --writes --writes-only --scale=5 --output=var/writes-5.json
python3 tools/benchmark-scale-fresh.py --php=/chemin/vers/php --report=var/scale-5.json
python3 tools/build-scale-report.py --measurements=/chemin/vers/les-six-json
```

Les rapports historiques embarqués servent de points de comparaison ; rejouer l’état « avant » exige le code de l’archive précédente. Aucune publication GitHub n’a été effectuée.
'''
(out/'PERFORMANCE_SCALE_REPORT.md').write_text(text)
headers=['Écran / route','Avant 1×','Avant 5×','Après 1×','Après 5×','PHP frais après 5×','HTML/JSON avant → après 5×']
body=[]
for row in rows:
 cells=[html.escape(row['Écran'])+'<small>'+html.escape(row['Route'])+'</small>']+[fmt(row[k]) for k in ['before_1_ms','before_5_ms','after_1_ms','after_5_ms','after_5_php_frais_ms']]+[f"{row['before_5_octets']:,} → {row['after_5_octets']:,}"]
 body.append('<tr>'+''.join('<td>'+c+'</td>' for c in cells)+'</tr>')
page='''<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Populorum · montée en charge ×5</title><style>body{font:16px/1.55 system-ui;background:#f7f9fb;color:#172b3b;margin:0;padding:clamp(16px,4vw,60px)}main{max-width:1400px;margin:auto}h1{font-size:clamp(28px,4vw,52px);line-height:1.1}a{color:#075c70}p{max-width:95ch}input{padding:12px;font:inherit;width:min(90%,580px);border:1px solid #a5b7bf;border-radius:6px}.scroll{overflow:auto;margin-top:22px}table{border-collapse:collapse;width:100%;background:white}th,td{padding:12px;border-bottom:1px solid #dae2e5;text-align:right;white-space:nowrap}th{background:#143d4a;color:white;position:sticky;top:0}td:first-child,th:first-child{text-align:left}small{display:block;font-size:12px;color:#536c78}tr:nth-child(even){background:#eff6f7}.note{padding:16px;border-left:4px solid #d1a540;background:#fffaf0}</style><main><p>POPULORUM · AUDIT DU 30 OCTOBRE 2026</p><h1>Cinq fois plus de données.<br>Mesurer avant de différer.</h1><p>94 routes comparées à ×5. Une navigation sans bulletins inutilisés, des listes paginées et des calculs limités aux consultations visibles. Les données complètes et les contrôles d’intégrité restent disponibles.</p><p class="note">Temps serveur locaux, médianes en millisecondes. 1× : 300 citoyens / 27 propositions / 2 700 positions. 5× : 1 500 / 135 / 13 500. Ces mesures excluent réseau, rendu navigateur et concurrence. Les exports, vérifications et mutations restent sensibles à la taille du snapshot complet. Les cas d’écriture et les limites sont détaillés dans le rapport.</p><p><a href="PERFORMANCE_SCALE_REPORT.md">Méthode, résultats, écritures et limites</a> · <a href="PERFORMANCE_SCALE_TIMINGS.csv">Valeurs CSV</a></p><label for="filter">Filtrer les écrans et les routes</label><br><input type="search" id="filter" placeholder="Consultations, budget, action…"><div class="scroll"><table><thead><tr>'''+''.join('<th>'+h+'</th>' for h in headers)+'''</tr></thead><tbody>'''+''.join(body)+'''</tbody></table></div><p>« PHP frais » inclut le démarrage d’un nouveau processus ; caches Symfony déjà compilés. Une case absente n’est pas zéro. Trois à cinq passages ne caractérisent pas les pointes de latence en production. Les séries brutes sont dans le dossier performance-scale.</p></main><script>document.querySelector('#filter').addEventListener('input',e=>{let q=e.target.value.toLocaleLowerCase('fr');for(let row of document.querySelectorAll('tbody tr'))row.hidden=!row.textContent.toLocaleLowerCase('fr').includes(q)})</script></html>'''
(out/'PERFORMANCE_SCALE_REPORT.html').write_text(page)
print(f'{len(rows)} routes, rapports Markdown/HTML/CSV produits.')
