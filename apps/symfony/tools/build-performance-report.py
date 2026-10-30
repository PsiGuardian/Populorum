#!/usr/bin/env python3
"""Publish measured before/after timings as Markdown, CSV and a standalone filterable HTML table."""
import argparse, csv, html, json
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--before', required=True)
parser.add_argument('--after', required=True)
parser.add_argument('--stream', required=True)
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
docs = root / 'docs'
raw_dir = docs / 'performance'
raw_dir.mkdir(exist_ok=True)
reports = {}
for key, path in [('before', args.before), ('after', args.after), ('stream', args.stream)]:
    text = Path(path).read_text()
    reports[key] = json.loads(text)
    (raw_dir / (key + '.json')).write_text(text)
before = {p['path']:p for p in reports['before']['pages']}
after = reports['after']['pages']
phases = ['db_read','json_decode','workspace','public_state','convergence','page_data','twig','json_encode','request']

def value(page, metric='warm', key='medianMs'):
    return page.get(metric, {}).get(key) if page else None

def fr(number, digits=1):
    return '—' if number is None else f'{number:.{digits}f}'.replace('.', ',')

def gain(old, new):
    return (1-new/old)*100 if old else None

rows = []
for page in after:
    old = before.get(page['path'])
    row = {'ecran':page['label'], 'route':page['path'], 'statut_avant':old['status'] if old else '', 'statut_apres':page['status'], 'avant_ms':value(old), 'apres_ms':value(page), 'gain_pourcent':gain(value(old),value(page)), 'n_avant':old['warm']['samples'] if old else '', 'n_apres':page['warm']['samples'], 'p95_avant_ms':value(old,key='p95Ms'), 'p95_apres_ms':value(page,key='p95Ms'), 'processus_frais_avant_ms':value(old,'freshProcess'), 'processus_frais_apres_ms':value(page,'freshProcess'), 'handle_frais_avant_ms':value(old,'freshHandle'), 'handle_frais_apres_ms':value(page,'freshHandle'), 'octets_avant':old['bytes'] if old else '', 'octets_apres':page['bytes']}
    for phase in phases:
        row['avant_'+phase+'_ms'] = old.get('phases',{}).get(phase,{}).get('medianMs') if old else None
        row['apres_'+phase+'_ms'] = page.get('phases',{}).get(phase,{}).get('medianMs')
    rows.append(row)
with (docs/'PERFORMANCE_TIMINGS.csv').open('w', newline='', encoding='utf-8-sig') as out:
    writer=csv.DictWriter(out, fieldnames=rows[0].keys(), delimiter=';');writer.writeheader();writer.writerows(rows)

intro = f'''# Mesures et optimisation des performances — 30 octobre 2026

{len(before)} routes comparées avant/après, plus la nouvelle actualisation courte : {len(after)} mesures après optimisation. Toutes répondent HTTP 200 dans cette campagne. Les seize rubriques principales, trois fiches détaillées, un personnage, la connexion, la vérification, les API représentatives, un export et tous les 67 formulaires d’action sont couverts. Les autres alias, langues, filtres et identifiants dynamiques utilisent ces mêmes modèles ; ils ne sont pas chacun chronométrés. Les écrans internes du bundle Profiler Shield ne sont pas inclus.

## Méthode et limites

PHP {reports['after']['php']}, Symfony demo, APP_DEBUG=0, SQLite. Fixture identique : 300 personnages, 27 propositions, 2 700 prises de position et leurs historiques ; les missions et dossiers de devis sont initialement vides. Aucun laboratoire utilisateur n’a été utilisé : chaque campagne utilise une copie SQLite jetable. Les mesures concernent les GET, pas la durée des écritures ni une montée en charge.

- **À chaud** : durée de `HttpKernel::handle`, kernel réutilisé, cinq passages pour les pages et trois pour les formulaires. La première requête de chaque écran est mesurée séparément dans les JSON. Le ramasse-miettes est sollicité entre les passages pour ce banc séquentiel, hors intervalle chronométré.
- **Processus frais** : médiane de trois exécutions d’un nouveau PHP, caches Symfony déjà compilés. Inclut lancement du processus, autoload et initialisation ; ce n’est pas une mesure de cache disque entièrement froid. La durée interne de `handle` est aussi fournie dans le CSV et le JSON.
- **p95** : rang le plus proche supérieur. Avec trois ou cinq observations, il correspond au maximum de cette petite série. Il ne constitue pas une estimation fiable de la queue de latence en production.
- Aucun réseau HTTP, transfert, rendu/LCP du navigateur, charge concurrente ou configuration PHP-FPM n’est inclus. OPcache CLI est désactivé dans le runtime de mesure. Les fluctuations de la machine partagée restent visibles ; certains écrans complets peuvent varier ou être légèrement plus lents en processus frais. Aucune promesse de délai absolu sur une autre machine.

## Résultat principal : occupation prolongée du processus PHP

L’ancien `EventSource` de la page consultations utilisait `/api/v1/events`. Une exécution du corps de ce flux a occupé le processus pendant **{fr(reports['stream']['streamMs']/1000,3)} secondes**, avec {reports['stream']['bytes']:,} octets émis. Ce chiffre décrit l’occupation du processus, pas le délai avant le premier octet. Sur un serveur PHP intégré à un seul processus, une requête bloquante peut faire attendre les autres pages.

L’interface utilise maintenant `/api/v1/live-results` : une requête courte toutes les cinq secondes, sans chevauchement, annulée à la sortie et suspendue quand l’onglet est masqué. Les échecs espacent les tentatives. Le flux historique reste disponible pour compatibilité mais n’est plus ouvert par cette interface. L’actualisation peut donc arriver jusqu’à environ cinq secondes après un changement ; le décompte initial et l’actualisation manuelle restent disponibles sans JavaScript.

## Modifications et contreparties

| Traitement | Modification | Risque maîtrisé / limite |
| --- | --- | --- |
| Convergence | Calcul uniquement sur l’écran concerné et sur les douze couples visibles après filtrage. | Les résultats de chaque page sont comparés exactement à l’analyse exhaustive dans les tests. |
| Résultats électoraux | Index des votes par consultation avant les décomptes. | Même règle familiale, mêmes admissibilités figées et mêmes totaux ; recalcul de l’échéance à chaque requête. |
| Lecture des pages | Vue `page_state` plus légère, versionnée, stockée avec le snapshot dans la même ligne et transaction SQLite. | Ne sert ni aux mutations, ni aux exports complets, ni à vérifier une signature ; reconstruction des anciennes vues et invalidation automatique si le snapshot est modifié seul. |
| Historiques mentaux | Les pages courantes n’en décodent plus tous les détails. | Les portraits, les douze réponses et les exports lisent le snapshot complet ; aucune suppression de l’historique de référence. |
| Liste des consultations | Totaux et actions dans la liste ; votes nominatifs, familles et texte intégral sur la fiche détaillée. | Un clic supplémentaire pour le détail, visible et utilisable sans JavaScript. |
| Comptes publics | Index SQLite dérivé des 4 322 lignes DGFiP, lecture de cinquante lignes seulement, filtres préparés. | Empreinte de la source vérifiée ; retour à l’archive officielle si l’index manque, est obsolète ou illisible. |
| Fichiers publics | Mémoïsation des statistiques et agrégats dans les services réinitialisables. Chartes chargées seulement à leur consultation. | Aucun cache de décision ou de données privées partagé entre habitants. |
| JSON | Encodage natif des tableaux aux schémas déjà définis, sans normalisation d’objets inutile. | Export et signatures testés ; toutes les données complètes de l’API d’état sont conservées. |
| Diagnostics | En-tête `Server-Timing` activable par `POPULORUM_TIMING=1`. | Désactivé par défaut ; durées seulement, aucun corps, nom, jeton ou paramètre. |

La vue de lecture ajoute une copie dérivée en base et un travail d’encodage à chaque écriture. La source complète, le contrôle d’intégrité et la projection métier transactionnelle sont conservés : ce lot privilégie la lecture sans promettre une accélération des écritures.

## Temps par écran et route

Toutes les valeurs de durée sont en millisecondes. « Frais » inclut le nouveau processus PHP ; « chaud » n’inclut pas ce démarrage. Une case vide signifie non applicable, pas zéro.

| Écran | Route | Chaud avant | Chaud après | Gain chaud | Frais avant | Frais après |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
'''
md = [intro]
for row in rows:
    md.append(f"| {row['ecran']} | `{row['route']}` | {fr(row['avant_ms'])} | {fr(row['apres_ms'])} | {fr(row['gain_pourcent'])} % | {fr(row['processus_frais_avant_ms'])} | {fr(row['processus_frais_apres_ms'])} |\n")
md.append('''
## Décomposition des traitements

Le [CSV complet](PERFORMANCE_TIMINGS.csv) contient les durées médianes avant/après de chaque phase, ainsi que les échantillons, p95, tailles et temps en processus frais. Les mesures brutes sont dans [avant](performance/before.json), [après](performance/after.json) et [flux historique](performance/stream.json). Le [tableau HTML filtrable](PERFORMANCE_REPORT.html) affiche aussi la décomposition de chaque ligne.

| Phase | Contenu | Imbrication |
| --- | --- | --- |
| `db_read` | Lecture de la ligne SQLite | Incluse dans `workspace` |
| `json_decode` | Décodage du snapshot ou de sa vue de lecture | Incluse dans `workspace` |
| `workspace` | Chargement du laboratoire, création initiale ou reconstruction éventuelle | Incluse dans la requête |
| `public_state` | Projection publique et, lorsque nécessaire, décomptes | Incluse dans la requête |
| `convergence` | Comparaison des couples visibles | Incluse dans `page_data` |
| `page_data` | Filtres, calculs et sources propres à la page | Incluse dans la requête |
| `twig` | Génération HTML | Incluse dans la requête |
| `json_encode` | Génération JSON native | Incluse dans la requête |
| `request` | Du premier événement principal à la réponse Symfony | Exclut l’initialisation antérieure du kernel et la terminaison |

Ces phases sont **inclusives** : ne pas les additionner. Les médianes sont calculées séparément, elles ne s’additionnent pas non plus comme les phases d’une requête unique. Les coûts non nommés (routage, sécurité, traduction, préparation des formulaires…) restent dans la durée totale. La durée `terminate` est donnée à part dans les JSON. Il s’agit d’une instrumentation ciblée, pas d’une trace exhaustive de chaque fonction PHP.

## Reproduire

Depuis `apps/symfony`, après installation et migrations :

```bash
php tools/benchmark-pages.php --samples=5 --output=var/performance.json
python3 tools/benchmark-fresh.py --report=var/performance.json --php=php
```

Le deuxième outil est facultatif et utilise uniquement la bibliothèque standard Python. Il ajoute les nouveaux processus PHP. Pour reconstruire l’index public après remplacement de l’archive DGFiP :

```bash
python3 tools/build-public-index.py
```

Le test optionnel `php tools/benchmark-stream.php` occupe volontairement un processus pendant environ vingt secondes pour observer l’ancien flux ; il s’exécute en CLI sur une base jetable.

Le premier chargement d’un nouveau laboratoire reste plus coûteux : création, signature et projections initiales. Il est isolé dans `firstWorkspaceAndRequest` des JSON, avec un seul échantillon par campagne ; il n’est pas mélangé aux médianes des pages.

## Mise à jour d’une installation existante

Conserver `var/`, `.env.local` et les clés. Mettre à jour le code et les dépendances, puis exécuter la migration additive et reconstruire les caches :

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=demo
php bin/console cache:clear --env=demo
```

La migration `Version20261030150000` ajoute uniquement la colonne de lecture et son déclencheur d’invalidation. Les vues des anciens laboratoires se reconstruisent à la première lecture, sans modifier les votes, la révision ou le journal. Ne pas remplacer une base utilisateur par la fixture livrée.

## Travail restant côté hébergement

Le démarrage et l’autoload restent une part importante des mesures en processus frais. Vérifier l’activation d’OPcache dans le PHP qui sert réellement le site et la bonne préparation du cache Symfony. Aucun réglage système n’a été changé et aucun gain OPcache n’est inclus dans ces chiffres. L’exemple `config/performance/php.ini.example` garde la vérification des fichiers actifs pour éviter de servir silencieusement du vieux code pendant le développement. Les vérifications de signature et les exports complets restent volontairement exhaustifs. Ce banc ne valide ni le dimensionnement d’une ville réelle ni des milliers d’utilisateurs concurrents.

Sources techniques officielles consultées le 30 octobre 2026 : [Symfony — performances](https://symfony.com/doc/current/performance.html), [PHP — OPcache](https://www.php.net/manual/en/opcache.configuration.php), [PHP — serveur intégré et requêtes bloquantes](https://www.php.net/manual/en/features.commandline.webserver.php).
''')
(docs/'PERFORMANCE_REPORT.md').write_text(''.join(md))
htmlrows=[]
for row,page in zip(rows,after):
    old=before.get(page['path']); detail=[]
    for phase in phases:
        detail.append('<tr><th>'+html.escape(phase)+'</th><td>'+fr(row['avant_'+phase+'_ms'],3)+'</td><td>'+fr(row['apres_'+phase+'_ms'],3)+'</td></tr>')
    htmlrows.append('<tr data-name="'+html.escape(row['ecran']+' '+row['route'],quote=True)+'"><th scope="row">'+html.escape(row['ecran'])+'<small>'+html.escape(row['route'])+'</small></th><td>'+fr(row['avant_ms'])+'</td><td>'+fr(row['apres_ms'])+'</td><td>'+fr(row['gain_pourcent'])+' %</td><td>'+fr(row['processus_frais_avant_ms'])+'</td><td>'+fr(row['processus_frais_apres_ms'])+'</td><td><details><summary>Traitements</summary><table><thead><tr><th>Phase</th><th>Avant ms</th><th>Après ms</th></tr></thead><tbody>'+''.join(detail)+'</tbody></table><p>Phases inclusives : ne pas additionner. N='+str(row['n_apres'])+' à chaud ; p95 après '+fr(row['p95_apres_ms'])+' ms. HTML/JSON après : '+str(row['octets_apres'])+' octets. HTTP '+str(row['statut_apres'])+'.</p></details></td></tr>')
report_html='''<!doctype html><html lang="fr"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Populorum · performances mesurées</title><style>body{font:16px/1.5 system-ui;background:#f6f6f0;color:#17251c;margin:0}main{max-width:1500px;margin:auto;padding:32px}h1{font-size:2.4rem;line-height:1.15}p{max-width:90ch}.note{padding:20px;background:#e8eee3;border-left:4px solid #526a45}.scroll{overflow:auto;background:white;border:1px solid #ccd4c8;border-radius:10px}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:14px;border-bottom:1px solid #dfe4db;vertical-align:top}thead{background:#e7ecdf}small{display:block;font:12px ui-monospace;overflow-wrap:anywhere;color:#546150}td{font-variant-numeric:tabular-nums}details table{font-size:13px}details p{font-size:13px}input{font:inherit;padding:12px;width:min(90%,550px);margin:10px 0 24px;border:1px solid #9aa98f;border-radius:8px}summary{cursor:pointer}a{color:#325b2c}@media(max-width:700px){main{padding:16px}h1{font-size:1.8rem}th,td{padding:9px}}</style><main><p>POPULORUM / MESURES DU 30 OCTOBRE 2026</p><h1>Moins de travail à chaque page.</h1><p>93 routes comparées, plus une actualisation courte. Toutes les valeurs sont en millisecondes ; chiffres de cette machine de test, sans réseau ni rendu navigateur. Cinq passages à chaud pour les pages, trois pour les formulaires, trois processus frais. Les phases détaillées sont inclusives.</p><p class="note">L’ancien flux SSE a occupé un processus pendant '''+fr(reports['stream']['streamMs']/1000,3)+''' secondes. L’interface utilise désormais de courtes requêtes espacées, suspendues dans les onglets masqués. Les historiques et contrôles complets sont conservés.</p><p><a href="PERFORMANCE_REPORT.md">Méthode, limites, migration et explications</a> · <a href="PERFORMANCE_TIMINGS.csv">Toutes les valeurs en CSV</a></p><label for="filter">Filtrer les écrans ou les routes</label><br><input id="filter" type="search" placeholder="Ex. budget, consultation, tender…"><div class="scroll"><table><thead><tr><th>Écran / route</th><th>Chaud avant</th><th>Chaud après</th><th>Gain chaud</th><th>Frais avant</th><th>Frais après</th><th>Décomposition</th></tr></thead><tbody>'''+''.join(htmlrows)+'''</tbody></table></div><p>Une case vide n’est pas zéro. « Frais » inclut le démarrage d’un nouveau PHP avec caches Symfony déjà compilés. Le p95 de ces petites séries correspond au maximum observé. Les routes d’export et de vérification restent exhaustives ; toute différence isolée en processus frais doit être interprétée avec les limites de la mesure.</p></main><script>document.querySelector('#filter').addEventListener('input',e=>{const q=e.target.value.toLocaleLowerCase('fr');document.querySelectorAll('tr[data-name]').forEach(r=>r.hidden=!r.dataset.name.toLocaleLowerCase('fr').includes(q));});</script></html>'''
(docs/'PERFORMANCE_REPORT.html').write_text(report_html)
print(f'{len(rows)} écrans et routes documentés ; méthodes et phases exportées.')
