# Expression individuelle et suffrage familial — lot 0.5

Règle demandée le 29 octobre 2026 : **chaque citoyen conserve son raisonnement et sa prise de position ; chaque famille compte au maximum une voix dans l’adoption d’une proposition, quelle que soit sa taille.** Elle est intégrée à l’article 11 de la charte version 1.1, dans les 28 langues documentaires, et aux spécifications française et anglaise.

## Familles du démonstrateur

Les 300 personnages sont répartis dans des groupes fictifs déclarés de tailles variées. Les identifiants ne sont pas déduits du nom, de liens généalogiques, de naissances ou de données sensibles. « Famille » désigne ici ce groupe déclaré : le logiciel ne prétend pas établir la composition d’une famille réelle. Chaque personnage a un rattachement au maximum par consultation. Les membres et la règle sont copiés dans le snapshot signé lors de l’ouverture.

La création d’un personnage ou d’un compte ne crée pas une nouvelle voix familiale. Un personnage sans famille admissible peut exprimer une position, visible dans le décompte individuel, mais celle-ci n’a pas de poids d’adoption. La vérification et l’admission des familles réelles ne sont pas simulées par une simple déclaration libre dans un formulaire. Aucune commande publique ne permet de diviser son groupe pour obtenir plus de voix.

## Règle d’agrégation retenue

Chaque membre admissible dispose d’une prise de position active POUR, CONTRE ou ABSTENTION, avec son cheminement personnel. La décision familiale utilise la majorité stricte des POUR et CONTRE effectivement exprimés. Les abstentions ne sont pas des suffrages exprimés. Aucun membre n’est obligé d’adopter le raisonnement de sa famille.

| Positions des membres participants | Décision familiale | Poids dans le résultat |
| --- | --- | ---: |
| 3 POUR, 1 CONTRE, 4 abstentions | POUR | 1 |
| 1 POUR, 1 CONTRE | ABSTENTION | 1 |
| 2 abstentions, aucun POUR/CONTRE | ABSTENTION | 1 |
| Aucun membre participant | Non participante | 0 |

Le résultat final applique la majorité stricte des familles POUR et CONTRE. L’égalité n’adopte pas la proposition. Le quorum est le nombre de familles participantes divisé par les familles admissibles figées ; une famille abstentionniste participe au quorum. Une famille ne peut produire deux voix. Par exemple, douze POUR dans une famille ne l’emportent pas sur deux autres familles CONTRE : résultat familial 1 POUR / 2 CONTRE, malgré 12 POUR / 2 CONTRE individuels.

Ce choix de majorité interne est une règle de simulation explicitement publiée. Il peut être révisé pour de nouvelles consultations par une modification versionnée ; aucune règle ne change au milieu d’un vote déjà ouvert.

## Affichage et exports

La consultation affiche d’abord le résultat familial utilisé pour l’adoption. Le décompte individuel est présenté séparément, avec les douze réponses de chacun et leurs versions. Un détail montre, pour chaque famille fictive, ses participants, les positions de ses membres et sa décision unique. Les profils rappellent la distinction entre opinion individuelle et poids familial. Les événements SSE actualisent les deux synthèses et leur unité.

L’export de consultation conserve `rules.votingUnit=family`, `rules.familyAggregation=family-majority-1`, `eligibleFamilies`, `eligibleIds` et les prises de position individuelles. `tools/recount.php` reconstitue le résultat familial sans utiliser le moteur applicatif. La signature du snapshot protège notamment le rattachement figé. Un vote individuel changé peut modifier sa décision familiale, jamais lui ajouter une seconde voix.

Les consultations historiques gardent leurs règles antérieures, y compris celles à unité individuelle. Les anciens laboratoires ne sont pas réinterprétés silencieusement. Pour essayer le lot 0.5, ouvrir un nouveau laboratoire dans **Participer**. Les opinions et profils évolutifs restent présents : 2 700 prises de position courantes, 32 400 réponses, neuf propositions par ville.

## Implémentation et contrôles

- `FamilySuffrage` fige les rattachements et calcule les décisions familiales ; les appartenances dupliquées ou étrangères à l’admissibilité sont refusées.
- `SimulationEngine::tally` applique l’unité figée ; une unité ou une version inconnue est refusée.
- `MunicipalFixtureBuilder` déclare les familles fictives avant les votes et crée les consultations avec leur nouvelle règle.
- `vote_records` conserve les prises de position individuelles uniques ; les voix familiales sont des résultats calculés, pas des bulletins supplémentaires modifiables.
- `FamilySuffrageTest` vérifie poids des familles nombreuses, égalités, abstentions, non-participation, changement individuel, double rattachement, absence de rattachement, création d’un personnage, gel des membres et recalcul indépendant des 27 résultats.

Aucune modification des tables SQLite n’est nécessaire : les nouveaux attributs sont conservés dans les snapshots et projections JSON transactionnels. Les exigences d’identité civile, de validation des groupes réels, de confidentialité et de gouvernance restent des chantiers de déploiement réel distincts de cette fiction.

![Résultat familial distinct des positions individuelles](images/populorum-suffrage-familial.jpg)
