# Interface : comprendre, participer, vérifier

Version locale de revue du 30 octobre 2026. Aucune publication GitHub ni mise en ligne effectuée pour ce lot.

## Le parcours retenu

L’accueil devient un atelier de raisonnement : sélection d’un problème, arbre des relations déclarées, lecture des douze rubriques, ouverture de la fiche pour agir. Les cinq entrées principales utilisent des verbes et des noms concrets. Sources, expériences, mémoire, gouvernance et infrastructure restent accessibles dans un volet secondaire, automatiquement ouvert sur leurs pages.

La fiche expose immédiatement son arbre. Les boutons suivent le parcours comprendre → proposer une révision → donner son avis si une consultation est ouverte → vérifier les résultats. Les autorisations métier continuent à s’appliquer côté serveur : voir un bouton ne donne aucun droit supplémentaire.

Le formulaire de création/révision sépare la description du chemin et les rubriques du raisonnement, repliables et marquées « renseigné » ou « à compléter ». Les erreurs serveur ouvrent les rubriques concernées. Le brouillon incomplet reste autorisé. Les formulaires fonctionnent sans JavaScript ; le petit script d’interface ouvre seulement le texte visé par un lien du graphe.

## Les cinq questions de revue

| Question | Décision | Risque identifié | Réponse apportée |
|---|---|---|---|
| Est-ce visible ? | Arbre ouvert sur la fiche et présent dès l’accueil ; actions nommées. | Les nœuds longs surchargent le graphe. | Extrait bref dans le nœud, texte intégral dans la lecture structurée, région défilante au clavier. |
| Est-ce simple à comprendre ? | Trois étapes : comprendre, donner son avis, vérifier et réviser. | Confusion entre opinion, voix familiale et résultat. | Dépouillement familial conservé, intitulé « avis » et mentions fictives ; aucune règle de vote changée. |
| Peut-on l’améliorer ? | Choix d’un arbre, filtres de comparaison, pagination de douze paires, rubriques progressives. | Certaines fonctions deviennent moins immédiatement visibles. | Volet secondaire explicite et automatiquement ouvert sur la rubrique active ; routes conservées. |
| L’amélioration peut-elle causer problème ? | Les positions de l’arbre suivent les dépendances déclarées. | Lire une flèche comme preuve scientifique ; cycle ou élément isolé. | Aucune flèche inventée, avertissement sur les boucles, texte et relations conservés ; disposition sans valeur probante. |
| Comment y pallier ? | Séparation stricte entre données publiques, hypothèses et expériences fictives. | Prendre une tendance commune pour l’effet d’une proposition. | Méthode et limites visibles, pas de lien automatique entre contexte communal et effet expérimental. |

## Ce qui est relié au réel

`PublicEvidence` lit l’extrait SSMSI déjà archivé dans `data/research/ssmsi-2024-2025-three-cities.json`. Le site présente les quinze indicateurs disponibles pour Paris, Marseille et Le Havre en 2024 et 2025, leurs effectifs et leurs taux. L’écart est calculé sur les taux non arrondis, en points de taux pour mille. La comparaison par paires porte sur le signe de cet écart, sans test de significativité ni attribution causale.

Les cambriolages sont rapportés aux logements ; les autres indicateurs aux habitants. Une valeur absente, non numérique ou protégée n’est jamais remplacée par zéro. Le fichier source, son empreinte archivée, la date de publication et celle de consultation sont accessibles. Le fichier n’est pas un flux actualisé en direct. Aucune extrapolation aux propositions sur l’énergie, l’habitat ou l’école n’est effectuée.

Page officielle de référence vérifiée le 30 octobre 2026 :
https://www.data.gouv.fr/datasets/bases-statistiques-communale-departementale-et-regionale-de-la-delinquance-enregistree-par-la-police-et-la-gendarmerie-nationales

Le rapprochement des textes et les comparaisons d’expériences du simulateur restent séparés. Ces dernières portent toujours la mention « Résultats fictifs ». Cette version ne prétend pas disposer de preuves réelles d’efficacité des propositions, ni d’un système universel d’inférence causale.

## Cadre institutionnel

L’accueil, le bandeau global et une introduction avant le texte de la charte précisent que le projet suppose un système d’État existant et s’inscrit en complément de ses institutions. Ce rôle doit être maintenu tant que les informations disponibles ne permettent pas un examen suffisant des causes et conséquences. Une connaissance exhaustive n’est pas promise et ne déclenche aucun remplacement automatique des institutions.

Le texte historique de la charte, ses traductions et les versions figées des consultations sont préservés : l’encadré institutionnel est un ajout explicatif commun.

## Vérification

- 53 tests PHPUnit réussis, 52 046 assertions, incluant les taux publics, les dénominateurs, l’absence de source, l’ordre causal, les boucles et les nouveaux parcours HTTP.
- 99 contrôles d’intégration, 64 contrôles de correction, 12 sondes d’audit réussis.
- Analyse statique PHP et syntaxe Twig validées.
- Navigateur : accueil Symfony, lien du graphe ouvrant son explication, changement d’indicateur réel ; les unités et les écarts changent bien.
- Largeur mobile : HTML réellement rendu par Symfony inspecté dans un cadre de 390 pixels. Largeur utile 375 pixels et largeur du document 375 pixels : pas de débordement de page. Le graphe garde son propre défilement horizontal. Ce contrôle de styles n’est pas un essai sur téléphone physique.

La recette antérieure reste disponible dans `COMPLETION_REVIEW.md`. Les résultats ci-dessus concernent ce lot et ne remplacent pas un audit d’accessibilité complet ni une démonstration de causalité.
