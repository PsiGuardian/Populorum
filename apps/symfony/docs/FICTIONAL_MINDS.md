# Personnages, raisonnements et apprentissage fictifs — 0.4

Les 300 personnages possèdent chacun neuf décisions locales et douze réponses par décision : **2 700 votes et 32 400 réponses dans l’état courant**. Les tours précédents restent consultables. Il s’agit d’un modèle narratif déterministe écrit en PHP, sans appel à une IA externe, diagnostic psychologique ou estimation des opinions des habitants réels.

## Portrait individuel

Chaque profil combine six priorités (sécurité, libertés, entraide, environnement, budget, accessibilité), une sensibilité aux retours, une ouverture aux informations, un attachement à l’interprétation précédente, une tolérance à l’incertitude et une confiance initiale dans l’essai. Douze voix narratives et des expressions/scènes quotidiennes modifient le ton : direct, analytique, familier, interrogatif, chaleureux, bref, imagé, formel, enthousiaste, sceptique, nuancé ou pratique. La voix ne dépend ni de l’aptitude à apprendre ni du vote. Il n’existe aucun score de valeur humaine ni classement entre profils.

Ces paramètres sont des coefficients de fiction, pas des mesures scientifiques. Ils ne sont pas associés à une origine, une religion, une profession réelle ou une donnée sensible. Deux personnes qui s’expriment de la même manière peuvent évoluer différemment.

## Tours et réactions aux erreurs

Le tour 0 présente une première lecture sans résultat de pilote. Les tours 1 et 2 apportent des contre-factuels explicitement inventés : amélioration partielle, résultat ambigu et coûteux, dégradation avec report, amélioration répétée accompagnée d’un accès inégal. Le profil met à jour une attente propre à chaque proposition, avec une réaction plus faible ou plus forte selon ses paramètres. Un contre-indice est moins intégré lorsqu’il contredit une attente à laquelle le personnage est attaché. L’apprentissage rapide peut aussi amplifier une conclusion trompeuse : il n’est pas présenté comme une supériorité.

La règle d’évolution est publiée dans `FictionalMind::evolve` : l’écart entre attente et signal fictif est pondéré par la réactivité, l’ouverture, l’attachement et la fiabilité fictive du retour. Les valeurs sont bornées. La décision combine cette attente, les priorités, une réserve propre au couple personnage/proposition et une zone d’abstention. Cette règle est un ressort narratif simplifié, sans calibration psychométrique ni représentativité statistique.

Le jeu initial conserve trois versions de chaque profil. Les nouveaux tours sont **déclenchés explicitement**, pas par le temps passé sur le site. Un personnage de gouvernance fictive peut lancer le tour suivant jusqu’au tour 8. Les votes fermés, les personnages sans consentement ou suspendus et les choix déjà repris manuellement ne sont pas recalculés. Un tour est protégé par CSRF, verrou optimiste, contrôle de rôle, anti-rejeu et journal signé.

## Douze réponses

Chaque dossier répond à : problème, cause directe, causes indirectes, causes probables, causes observées, solution, bénéfices, risques, indicateurs, imprévus, alternatives et révision. Les documents publics cités décrivent un contexte ; ils ne deviennent jamais des preuves d’efficacité. Au tour 0, aucun imprévu déjà observé n’est inventé. Aux tours suivants, les observations narratives sont annoncées comme fictives.

Le vote garde la version figée de la proposition, la version du profil, le tour, le modèle et l’empreinte du texte complet. Le dossier régénéré doit correspondre à cette empreinte ; sinon sa consultation est refusée. Les anciens profils et réponses ne sont pas réécrits quand le personnage change d’avis. Un changement manuel conserve le raisonnement précédent dans l’historique et retire l’attribution d’un raisonnement automatique au nouveau choix.

## Fichiers et reproduction

- `src/Service/FictionalMind.php` : profils, évolution, styles et douze réponses.
- `src/Service/MentalSimulation.php` : tours, admissibilité, historiques et contrôle des empreintes.
- `data/scenario.json.gz` : fixture complète compressée sans perte, sans clé privée.
- `data/citizen-deliberations.ndjson.gz` : 2 700 dossiers courants complets, un JSON par ligne ; les 32 400 réponses sont physiquement présentes dans cette archive.
- `tools/build-municipal-fixtures.php` : reconstruit les deux archives et une vue JSON développée locale `data/scenario.json`. Cette vue dérivée n’est pas versionnée, pour éviter de dupliquer plusieurs mégaoctets ; le code source reste développé et lisible.

```bash
php tools/build-municipal-fixtures.php
composer test
```

Les tirages dépendent d’identifiants fictifs et d’une version du modèle, sans hasard global ni réseau. Toute évolution sémantique ultérieure du modèle devra porter une nouvelle version et conserver le lecteur de l’ancienne : changer les phrases de la version existante invaliderait les empreintes des laboratoires déjà créés.

## Parcours

Dans **Participer**, ouvrir un nouveau laboratoire pour disposer du millésime 0.4 ; les espaces antérieurs sont conservés. Choisir un personnage, puis **Mon portrait, mes raisonnements et leurs versions**. Sélectionner une proposition et un tour. Chaque dossier est exportable en JSON depuis la page. Les participations d’une consultation renvoient aux douze réponses de chaque personnage.

Pour avancer, choisir un personnage de gouvernance, par exemple **Personnage Paris 004 (u10)**, puis **Jouer le prochain tour fictif** et confirmer. Un seul personnage ne reçoit aucune voix supplémentaire : il déclenche uniquement une étape narrative dans le laboratoire.

Lecture publique JSON dans son laboratoire : `/characters/u1?consultation=c1&round=0&format=json`. Personnage, consultation ou tour introuvable : 404. Aucun secret de compte ou clé privée n’est inclus.

## Recette

Les tests contrôlent les 2 700 dossiers et leurs douze rubriques, leur correspondance exacte avec l’archive, les neuf décisions locales par personnage, les trois choix possibles, les douze voix, des réactions différentes au même contre-indice, les anciens textes, la détection de falsification, les votes clos/manuels, le contrôle de rôle, la limite de huit tours et les signatures. Le portrait et l’export JSON sont testés par Symfony HttpKernel. La recette navigateur a exercé le passage effectif du tour 2 au tour 3 et la consultation des tours antérieurs.

Cette simulation ne prétend pas reproduire toute la complexité d’une personne réelle : fatigue, relations sociales, mémoire sélective et évolution des priorités pourraient former de nouveaux modèles, à définir et tester explicitement.
