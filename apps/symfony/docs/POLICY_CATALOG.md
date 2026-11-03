# Catalogue de mesures politiques sourcées — Populorum

## Objectif et limites

Cette fonctionnalité propose des consultations **fictives, exploratoires et non représentatives**
sur des intentions politiques et des propositions de loi documentées.
Le vote n'a aucun effet juridique et ne représente pas une élection, une enquête
d'opinion ou une preuve d'approbation par un corps électoral.

- L'identité politique de la source n'apparaît **pas avant clôture** sur la fiche
  ni sur le bulletin ; la référence officielle et l'attribution sont conservées
  hors de la présentation publique et deviennent visibles après clôture.
- Il est possible de retrouver l'auteur d'un texte par recherche externe :
  cette présentation ne garantit pas un aveuglement absolu.
- **Une famille de test d'un membre par compte de test**, compte lié à une IP
  pseudonymisée : aucun contrôle de l'identité ou de l'unicité des personnes.
  Changer d'IP peut permettre de créer un autre profil, ce qui rend le résultat
  impropre à une décision réelle.
- Participation libre, sans quorum interne. Le règlement et les modalités sont
  fixés pendant toute la consultation, mais de nouveaux profils peuvent
  participer jusqu'à la clôture (protocole distinct des consultations historiques
  dont l'admissibilité est figée à l'ouverture).
- Les réponses **pour / contre / abstention** sont révisables jusqu'à la clôture.
  Les révisions sont historisées. Résultats et provenance dévoilés après clôture.
- Un dossier déposé n'est pas nécessairement une loi adoptée. Les mesures
  de programme sont datées de leur édition ; elles ne sont pas les engagements
  actuels d'un acteur si celui-ci a changé de position.

## Sources et état du corpus

### Corpus de départ

Le fichier data/policy-programme-measures.json contient **45 mesures éditoriales**
attribuées en interne à trois programmes :
- Marine Le Pen, programme présidentiel de **2022** :
  https://rassemblementnational.fr/22-mesures
- L'Avenir en commun, édition **2025** :
  https://programme.lafranceinsoumise.fr/wp-content/uploads/2025/avenir_en_commun_2025.pdf
- Sarah Knafo, programme municipal de Paris **2026** :
  https://sarahpourparis.fr/Programme-Sarah-Knafo-Paris-2026.pdf

Les libellés sont reformulés pour la simulation, et les numéros/sections
permettent de retrouver l'original. Ils ne constituent pas 45 lois distinctes.

### Extension à 200 puis 1 000 dossiers vérifiables

Le Sénat publie l'export des **propositions de loi déposées** avec la date,
le numéro, le titre, le ou les auteurs, le lien et l'état du dossier.
Documentation :
https://data.senat.fr/aide/liste-des-propositions-de-loi-deposees-au-senat/

Dans le répertoire apps/symfony, télécharger le CSV et importer le catalogue
sans modifier la base principale :

~~~bash
mkdir -p var
curl -fLsS --retry 3 \
  'https://www.data.gouv.fr/api/1/datasets/r/7698274a-65f6-4687-8b2b-6961199b6922' \
  -o /tmp/populorum-senat-propositions.csv

python3 tools/import_policy_catalog.py \
  --senat-csv /tmp/populorum-senat-propositions.csv \
  --limit 200 --days 90
~~~

Lire les statistiques affichées : valid_candidates, inserted, database_total.
**Ne pas annoncer 200 mesures tant que database_total ne le confirme pas.**
Le CSV peut évoluer : si ses colonnes changent, l'importeur doit échouer
explicitement plutôt que d'inventer des lignes. Aucun téléchargement ne
s'exécute automatiquement lors de l'installation de Symfony.

Pour importer **1 000** mesures à partir du même export, répéter la commande
avec --limit 1000. L'import est idempotent et préserve les votes, les
clôtures et les références existantes (INSERT OR IGNORE).

L'export officiel est publié sous Licence Ouverte (Sénat). Le corpus de
programmes doit conserver la date et la référence de l'édition consultée.
Les titres sénatoriaux sont des **titres source non expertisés** : ils ne
restituent pas le détail d'un texte article par article. Ne pas faire une
simulation causale chiffrée sans analyse complémentaire de la proposition.

## Navigation et données

- /mesures : bibliothèque paginée, filtres France/Paris et recherche.
- /mesures/{id} : question neutre, axes de vérification, vote fictif.
- Le vote nécessite un compte actif créé via /compte-test.
- Les mesures nationales sont accessibles à toutes les communes, les
  mesures de Paris ne sont votables que par les profils rattachés à Paris.

Les nouvelles données sont dans **var/policy-catalog.sqlite**, séparée de
var/populorum.sqlite et des bases INSEE/DECP. Aucune migration des bases
existantes n'est nécessaire.

### Base source et traçabilité

La table policy_measures conserve origin_actor, source_url,
source_reference, source_date, legal_status et review_status.
Avant clôture, le contrôleur n'envoie pas les premiers champs au navigateur.
La table policy_votes contient un seul vote actif par couple
(mesure, citizenId) et policy_vote_history garde les révisions.

Les votes ne sont pas cryptographiquement certifiés comme les événements
du moteur principal ; ne pas les annoncer comme inviolables. L'importation
ne déduit pas une appartenance partisane des sénateurs et n'impose pas
une répartition artificielle entre partis ; la diversité éditoriale et
les similarités sémantiques entre dossiers doivent être vérifiées avant
une exploitation publique. Le dédoublonnage automatique ne traite que
les titres normalisés, pas toutes les reformulations équivalentes.

## Tests

~~~bash
python3 tests/policy_catalog_import_test.py
php vendor/bin/phpunit tests/PolicyCatalogTest.php
php bin/console lint:twig templates --env=demo
~~~

Les données, sources et positions doivent être vérifiées à chaque publication
pour tenir compte de leur évolution, avec sauvegarde de l'édition d'origine.
