# Mesures et optimisation des performances — 30 octobre 2026

93 routes comparées avant/après, plus la nouvelle actualisation courte : 94 mesures après optimisation. Toutes répondent HTTP 200 dans cette campagne. Les seize rubriques principales, trois fiches détaillées, un personnage, la connexion, la vérification, les API représentatives, un export et tous les 67 formulaires d’action sont couverts. Les autres alias, langues, filtres et identifiants dynamiques utilisent ces mêmes modèles ; ils ne sont pas chacun chronométrés. Les écrans internes du bundle Profiler Shield ne sont pas inclus.

## Méthode et limites

PHP 8.4.5, Symfony demo, APP_DEBUG=0, SQLite. Fixture identique : 300 personnages, 27 propositions, 2 700 prises de position et leurs historiques ; les missions et dossiers de devis sont initialement vides. Aucun laboratoire utilisateur n’a été utilisé : chaque campagne utilise une copie SQLite jetable. Les mesures concernent les GET, pas la durée des écritures ni une montée en charge.

- **À chaud** : durée de `HttpKernel::handle`, kernel réutilisé, cinq passages pour les pages et trois pour les formulaires. La première requête de chaque écran est mesurée séparément dans les JSON. Le ramasse-miettes est sollicité entre les passages pour ce banc séquentiel, hors intervalle chronométré.
- **Processus frais** : médiane de trois exécutions d’un nouveau PHP, caches Symfony déjà compilés. Inclut lancement du processus, autoload et initialisation ; ce n’est pas une mesure de cache disque entièrement froid. La durée interne de `handle` est aussi fournie dans le CSV et le JSON.
- **p95** : rang le plus proche supérieur. Avec trois ou cinq observations, il correspond au maximum de cette petite série. Il ne constitue pas une estimation fiable de la queue de latence en production.
- Aucun réseau HTTP, transfert, rendu/LCP du navigateur, charge concurrente ou configuration PHP-FPM n’est inclus. OPcache CLI est désactivé dans le runtime de mesure. Les fluctuations de la machine partagée restent visibles ; certains écrans complets peuvent varier ou être légèrement plus lents en processus frais. Aucune promesse de délai absolu sur une autre machine.

## Résultat principal : occupation prolongée du processus PHP

L’ancien `EventSource` de la page consultations utilisait `/api/v1/events`. Une exécution du corps de ce flux a occupé le processus pendant **21,005 secondes**, avec 293,164 octets émis. Ce chiffre décrit l’occupation du processus, pas le délai avant le premier octet. Sur un serveur PHP intégré à un seul processus, une requête bloquante peut faire attendre les autres pages.

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
| Accueil | `/` | 71,9 | 17,7 | 75,4 % | 212,5 | 165,3 |
| Territoires | `/territoires` | 75,5 | 19,5 | 74,2 % | 236,3 | 177,3 |
| Propositions | `/chemins` | 79,5 | 18,9 | 76,2 % | 224,7 | 176,2 |
| Consultations | `/consultations` | 190,0 | 51,9 | 72,7 % | 312,6 | 171,5 |
| Expériences | `/experiences` | 95,7 | 26,1 | 72,8 % | 235,2 | 155,7 |
| Convergence | `/convergence` | 75,6 | 26,7 | 64,7 % | 208,1 | 185,4 |
| Mémoire | `/memoire` | 91,8 | 23,9 | 74,0 % | 218,8 | 165,6 |
| Transparence | `/transparence` | 85,3 | 22,5 | 73,7 % | 230,4 | 172,7 |
| Infrastructure | `/infrastructure` | 81,0 | 23,4 | 71,1 % | 222,5 | 160,8 |
| Gouvernance | `/gouvernance` | 80,9 | 22,9 | 71,7 % | 206,1 | 151,9 |
| Participer | `/participer` | 89,0 | 27,0 | 69,6 % | 235,9 | 162,5 |
| Charte | `/charte` | 90,2 | 30,9 | 65,7 % | 236,5 | 229,0 |
| Récits | `/recits` | 89,1 | 25,1 | 71,8 % | 215,8 | 171,4 |
| Services et missions | `/services-et-missions` | 109,9 | 32,1 | 70,8 % | 234,3 | 194,1 |
| Budget | `/budget-et-priorites` | 130,2 | 36,2 | 72,2 % | 318,6 | 184,4 |
| Besoins et devis | `/besoins-et-devis` | 91,1 | 28,0 | 69,2 % | 220,3 | 199,7 |
| Proposition détaillée | `/proposals/fiche/p1` | 89,8 | 28,0 | 68,8 % | 227,5 | 197,7 |
| Consultation détaillée | `/consultations/fiche/c1` | 84,7 | 39,4 | 53,5 % | 220,2 | 199,1 |
| Expérience détaillée | `/experiments/fiche/e1` | 88,7 | 26,5 | 70,1 % | 214,5 | 211,4 |
| Personnage et raisonnement | `/characters/u1` | 102,8 | 61,8 | 39,9 % | 228,5 | 239,4 |
| Connexion | `/citizen/login` | 0,6 | 0,6 | 3,7 % | 148,6 | 150,7 |
| Vérification d’intégrité | `/verify` | 169,0 | 158,1 | 6,5 % | 308,8 | 310,3 |
| API état | `/api/v1/state` | 165,8 | 93,9 | 43,4 % | 299,1 | 216,2 |
| API propositions | `/api/v1/proposals` | 87,2 | 27,0 | 69,1 % | 244,2 | 170,9 |
| API résultats | `/api/v1/results` | 90,0 | 39,8 | 55,8 % | 211,7 | 175,7 |
| Export mémoire | `/export/memory` | 122,0 | 95,4 | 21,8 % | 214,0 | 192,4 |
| Actualisation courte (nouvelle) | `/api/v1/live-results` | — | 33,2 | — % | — | 161,0 |
| Action : tender.create | `/action/tender.create` | 101,2 | 36,4 | 64,1 % | 260,8 | 197,2 |
| Action : tender.question | `/action/tender.question` | 98,4 | 29,5 | 70,0 % | 235,9 | 300,9 |
| Action : tender.answer | `/action/tender.answer` | 109,5 | 34,3 | 68,7 % | 271,9 | 198,0 |
| Action : tender.bid | `/action/tender.bid` | 85,8 | 31,8 | 62,9 % | 257,8 | 202,8 |
| Action : tender.withdraw | `/action/tender.withdraw` | 93,0 | 30,3 | 67,4 % | 261,6 | 206,4 |
| Action : tender.open | `/action/tender.open` | 105,6 | 30,6 | 71,1 % | 234,1 | 186,1 |
| Action : tender.evaluate | `/action/tender.evaluate` | 103,7 | 33,1 | 68,1 % | 271,8 | 197,3 |
| Action : tender.alert | `/action/tender.alert` | 90,2 | 31,0 | 65,6 % | 260,7 | 172,8 |
| Action : tender.explain | `/action/tender.explain` | 87,4 | 31,0 | 64,5 % | 276,5 | 185,1 |
| Action : tender.participation | `/action/tender.participation` | 92,5 | 34,5 | 62,8 % | 256,1 | 183,0 |
| Action : tender.volunteer | `/action/tender.volunteer` | 92,8 | 31,6 | 65,9 % | 243,0 | 180,5 |
| Action : tender.validate | `/action/tender.validate` | 91,7 | 30,8 | 66,4 % | 230,1 | 246,6 |
| Action : tender.award | `/action/tender.award` | 92,9 | 31,4 | 66,2 % | 240,6 | 201,7 |
| Action : tender.amend | `/action/tender.amend` | 90,5 | 38,3 | 57,7 % | 276,2 | 192,1 |
| Action : tender.approve | `/action/tender.approve` | 90,3 | 29,5 | 67,4 % | 263,1 | 190,1 |
| Action : tender.delivery | `/action/tender.delivery` | 126,2 | 30,1 | 76,1 % | 276,1 | 182,4 |
| Action : tender.inspect | `/action/tender.inspect` | 116,1 | 26,4 | 77,3 % | 269,6 | 177,1 |
| Action : tender.pay | `/action/tender.pay` | 92,2 | 22,6 | 75,5 % | 258,5 | 194,9 |
| Action : tender.cancel | `/action/tender.cancel` | 96,8 | 22,0 | 77,3 % | 245,6 | 167,7 |
| Action : mission.cancel | `/action/mission.cancel` | 94,6 | 23,0 | 75,7 % | 258,2 | 178,1 |
| Action : worker.profile | `/action/worker.profile` | 104,1 | 24,2 | 76,8 % | 282,9 | 182,4 |
| Action : worker.availability | `/action/worker.availability` | 105,5 | 35,4 | 66,5 % | 284,2 | 203,4 |
| Action : mission.create | `/action/mission.create` | 93,5 | 33,7 | 63,9 % | 328,8 | 197,6 |
| Action : mission.offer | `/action/mission.offer` | 95,6 | 29,7 | 68,9 % | 242,1 | 195,0 |
| Action : mission.respond | `/action/mission.respond` | 115,3 | 27,4 | 76,2 % | 244,0 | 242,0 |
| Action : mission.progress | `/action/mission.progress` | 120,1 | 22,9 | 80,9 % | 260,5 | 202,3 |
| Action : mission.review | `/action/mission.review` | 99,6 | 24,0 | 75,9 % | 311,8 | 194,4 |
| Action : account.create | `/action/account.create` | 105,3 | 31,5 | 70,0 % | 333,1 | 209,6 |
| Action : account.append | `/action/account.append` | 93,1 | 37,1 | 60,1 % | 313,9 | 235,1 |
| Action : simulation.advance | `/action/simulation.advance` | 108,6 | 31,3 | 71,2 % | 251,3 | 246,5 |
| Action : proposal.transition | `/action/proposal.transition` | 97,2 | 27,9 | 71,3 % | 271,8 | 205,7 |
| Action : proposal.reformulate | `/action/proposal.reformulate` | 114,9 | 32,4 | 71,8 % | 231,8 | 211,8 |
| Action : governance.enact | `/action/governance.enact` | 113,8 | 27,1 | 76,1 % | 245,0 | 207,8 |
| Action : moderation.decide | `/action/moderation.decide` | 118,4 | 31,9 | 73,1 % | 253,8 | 200,8 |
| Action : moderation.confirm | `/action/moderation.confirm` | 98,8 | 34,3 | 65,3 % | 246,5 | 181,4 |
| Action : moderation.resolve | `/action/moderation.resolve` | 107,1 | 31,2 | 70,8 % | 254,7 | 166,6 |
| Action : integrity.review | `/action/integrity.review` | 93,9 | 32,4 | 65,5 % | 287,2 | 168,4 |
| Action : consultation.review | `/action/consultation.review` | 99,2 | 26,6 | 73,2 % | 250,4 | 185,1 |
| Action : citizen.preferences | `/action/citizen.preferences` | 96,4 | 27,1 | 71,9 % | 277,4 | 178,5 |
| Action : citizen.create | `/action/citizen.create` | 94,6 | 27,3 | 71,1 % | 280,9 | 182,6 |
| Action : citizen.consent | `/action/citizen.consent` | 100,3 | 23,6 | 76,4 % | 277,8 | 176,7 |
| Action : citizen.withdraw | `/action/citizen.withdraw` | 91,9 | 29,3 | 68,1 % | 251,5 | 182,2 |
| Action : citizen.update | `/action/citizen.update` | 119,2 | 30,6 | 74,3 % | 240,9 | 162,2 |
| Action : citizen.erase | `/action/citizen.erase` | 110,7 | 30,2 | 72,7 % | 217,7 | 168,1 |
| Action : proposal.create | `/action/proposal.create` | 106,5 | 39,7 | 62,7 % | 223,5 | 236,0 |
| Action : proposal.publish | `/action/proposal.publish` | 108,7 | 22,1 | 79,7 % | 246,1 | 195,6 |
| Action : proposal.revise | `/action/proposal.revise` | 121,8 | 41,9 | 65,7 % | 274,2 | 184,2 |
| Action : proposal.source | `/action/proposal.source` | 126,8 | 23,6 | 81,4 % | 240,9 | 185,2 |
| Action : proposal.node | `/action/proposal.node` | 124,0 | 24,4 | 80,3 % | 247,6 | 167,3 |
| Action : proposal.edge | `/action/proposal.edge` | 97,8 | 26,3 | 73,1 % | 285,9 | 160,6 |
| Action : territory.revise | `/action/territory.revise` | 108,2 | 25,0 | 76,9 % | 291,6 | 184,3 |
| Action : territory.adapt | `/action/territory.adapt` | 119,8 | 25,1 | 79,0 % | 310,4 | 186,9 |
| Action : consultation.open | `/action/consultation.open` | 100,0 | 31,4 | 68,6 % | 278,8 | 178,9 |
| Action : consultation.close | `/action/consultation.close` | 100,5 | 30,8 | 69,3 % | 250,8 | 173,2 |
| Action : vote.cast | `/action/vote.cast` | 96,5 | 25,8 | 73,3 % | 289,6 | 182,1 |
| Action : experiment.create | `/action/experiment.create` | 111,3 | 27,2 | 75,6 % | 238,8 | 179,4 |
| Action : observation.add | `/action/observation.add` | 89,7 | 29,2 | 67,5 % | 265,5 | 206,7 |
| Action : experiment.evaluate | `/action/experiment.evaluate` | 116,5 | 27,6 | 76,3 % | 254,4 | 226,5 |
| Action : finance.add | `/action/finance.add` | 93,7 | 25,9 | 72,4 % | 236,3 | 209,6 |
| Action : governance.propose | `/action/governance.propose` | 123,5 | 29,5 | 76,1 % | 262,1 | 193,7 |
| Action : governance.approve | `/action/governance.approve` | 99,7 | 23,6 | 76,4 % | 259,0 | 181,4 |
| Action : moderation.report | `/action/moderation.report` | 92,8 | 28,1 | 69,7 % | 269,6 | 183,7 |
| Action : moderation.appeal | `/action/moderation.appeal` | 89,2 | 30,2 | 66,2 % | 273,0 | 192,6 |
| Action : integrity.alert | `/action/integrity.alert` | 88,9 | 28,2 | 68,3 % | 238,0 | 203,0 |
| Action : node.configure | `/action/node.configure` | 92,0 | 27,8 | 69,7 % | 311,8 | 216,6 |
| Action : node.sync | `/action/node.sync` | 89,2 | 28,2 | 68,4 % | 328,8 | 184,3 |
| Action : resource.add | `/action/resource.add` | 92,0 | 23,7 | 74,2 % | 293,5 | 189,7 |

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

## Vérifications de cette livraison

99 tests PHPUnit, 53 644 assertions ; 99 contrôles d’intégration, 64 contrôles de correction et 12 sondes d’audit sans échec. PHPStan ne relève pas d’erreur ; les 25 modèles Twig, les 15 fichiers YAML et le schéma Doctrine sont valides.

Les nouveaux contrôles couvrent notamment l’égalité des décomptes avec le calcul complet, les couples de convergence filtrés et paginés, les vues anciennes/corrompues, l’isolation des laboratoires, les écritures concurrentes et les annulations de transaction, l’invalidation d’une vue après une modification externe du snapshot, les exports signés complets et l’égalité des 4 322 lignes de balance entre l’archive et l’index. L’index obsolète est refusé au profit de la source.

Vérification navigateur : actualisation visible par requêtes courtes, navigation vers l’accueil, accès au détail des familles et aux votes nominatifs, filtre du tableau HTML de performances. Ces vérifications ne constituent pas un test de charge ni un audit exhaustif de sécurité ou d’accessibilité.
