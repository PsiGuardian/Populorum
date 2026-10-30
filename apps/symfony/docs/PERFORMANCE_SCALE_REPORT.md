# Populorum — base cinq fois plus volumineuse

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

| Écran | Avant 1× | Avant 5× | Après 1× | Après 5× | PHP frais après 5× |
|---|---:|---:|---:|---:|---:|
| Accueil | 19.0 | 82.2 | 11.1 | 48.8 | 213.0 |
| Territoires | 19.0 | 79.6 | 8.1 | 47.5 | 220.7 |
| Propositions | 23.1 | 93.8 | 7.5 | 44.0 | 207.3 |
| Consultations | 36.9 | 158.7 | 32.5 | 102.9 | 289.8 |
| Expériences | 22.0 | 116.1 | 9.5 | 47.8 | 222.6 |
| Convergence | 22.3 | 120.0 | 12.0 | 46.6 | 227.4 |
| Mémoire | 17.9 | 109.7 | 10.9 | 57.5 | 267.6 |
| Transparence | 18.7 | 98.6 | 9.2 | 47.6 | 251.0 |
| Infrastructure | 18.5 | 105.5 | 8.1 | 44.1 | 229.3 |
| Gouvernance | 17.8 | 102.8 | 8.0 | 51.0 | 226.1 |
| Participer | 20.3 | 119.1 | 12.2 | 54.4 | 246.4 |
| Charte | 34.1 | 127.8 | 20.6 | 55.0 | 280.9 |
| Récits | 20.5 | 137.2 | 18.0 | 51.3 | 217.5 |
| Services et missions | 20.8 | 169.1 | 12.6 | 49.3 | 200.1 |
| Budget | 23.2 | 128.1 | 21.7 | 62.3 | 240.9 |
| Besoins et devis | 18.2 | 130.4 | 11.7 | 60.5 | 196.2 |
| Proposition détaillée | 29.8 | 120.9 | 12.1 | 63.2 | 244.2 |
| Consultation détaillée | 29.5 | 203.1 | 27.1 | 101.9 | 328.1 |
| Expérience détaillée | 29.6 | 129.2 | 10.7 | 45.7 | 221.4 |
| Personnage et raisonnement | 67.4 | 305.2 | 46.3 | 229.1 | 514.4 |
| Connexion | 0.8 | 0.6 | 0.5 | 0.7 | 165.2 |
| Vérification d’intégrité | 169.7 | 808.5 | 122.1 | 676.8 | 1095.9 |
| API état | 84.5 | 450.5 | 72.9 | 468.2 | 610.7 |
| API propositions | 30.9 | 156.6 | 23.5 | 107.7 | 235.1 |
| API résultats | 34.5 | 190.6 | 25.9 | 175.5 | 305.8 |
| Export mémoire | 114.6 | 445.1 | 72.5 | 456.1 | 542.3 |
| Actualisation courte (nouvelle) | 35.4 | 193.7 | 26.0 | 161.0 | 288.7 |

Les 94 routes du scénario 5× et leurs tailles HTML/JSON sont disponibles dans `PERFORMANCE_SCALE_TIMINGS.csv` et le tableau HTML filtrable. Les cas 1× avant et cinq simulations couvrent les 27 écrans et API, sans les 67 formulaires ; une case absente n’est jamais zéro. Les JSON conservent minimum, maximum, p95 et décomposition serveur.

## Cinq simulations indépendantes dans la même base

| Écran | Version finale, 1 simulation | Version finale, 5 simulations indépendantes |
|---|---:|---:|
| Accueil | 11.1 | 8.5 |
| Consultations | 32.5 | 26.8 |
| Budget | 21.7 | 16.7 |

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
| 1× avant incrémental | 47.6 | 232.7 | 233.3 | 490.2 |
| 1× après optimisations | 53.7 | 172.2 | 156.3 | 408.0 |
| 5× avant incrémental | 234.1 | 1062.5 | 1651.7 | 2940.7 |
| 5× après optimisations | 202.2 | 1100.4 | 801.5 | 2105.4 |

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

- before 1 : 27 routes ; fichier SQLite 8,691,712 octets avant les séries ; création initiale hors mesures par page.
- before 5 : 94 routes ; fichier SQLite 44,109,824 octets avant les séries ; création initiale hors mesures par page.
- before neighbors : 27 routes ; fichier SQLite 42,627,072 octets avant les séries ; création initiale hors mesures par page.
- after 1 : 94 routes ; fichier SQLite 9,289,728 octets avant les séries ; création initiale hors mesures par page.
- after 5 : 94 routes ; fichier SQLite 47,222,784 octets avant les séries ; création initiale hors mesures par page.
- after neighbors : 27 routes ; fichier SQLite 45,600,768 octets avant les séries ; création initiale hors mesures par page.

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
