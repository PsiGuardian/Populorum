# Catalogue de propositions de loi officielles — Populorum

## Périmètre

**Seules les propositions de loi déposées officiellement au Sénat sont présentées
et soumises à la simulation de vote.** Les programmes électoraux, promesses,
intentions, démonstrations fictives, propositions de résolution, projets du
Gouvernement, textes promulgués, caduques ou rejetés ne figurent pas dans
l'interface des propositions.

La source parlementaire est l'export « Liste des propositions de loi déposées
au Sénat » : https://data.senat.fr/aide/liste-des-propositions-de-loi-deposees-au-senat/
Le jeu de données est sous Licence Ouverte :
https://www.data.gouv.fr/datasets/travaux-legislatifs-senat

Cet export contient les intitulés, numéros, dates de dépôt, auteurs, liens de
dossiers et états parlementaires. **Un dépôt n'est pas une loi votée**, et le
vote proposé dans Populorum porte sur l'idée résumée par l'intitulé ; il ne
remplace pas l'examen des articles de loi.

Les auteurs et sources sont conservés pour vérification mais ne figurent pas sur
le bulletin avant clôture. Un titre de loi peut permettre de retrouver son
auteur par recherche externe : cette neutralité n'est pas un anonymat absolu.

## Transition depuis les anciennes propositions fictives

- Le fichier de 45 mesures provenant de programmes électoraux a été retiré du
  dépôt ; elles ne sont plus importables automatiquement.
- Les anciennes routes /proposals et /consultations redirigent vers /mesures.
- Les anciennes actions Web/API de création, modification et vote de
  propositions fictives sont désactivées.
- Les fiches de programmes précédemment importées dans la base annexe sont
  **masquées et interdites au vote**, pas détruites ; leurs votes antérieurs
  restent conservés. Les bases de simulation historiques conservent aussi
  leurs événements, pour ne pas effacer la mémoire ni fausser leurs empreintes
  d'intégrité. Les anciens contenus pédagogiques d'autres pages demeurent
  distincts de la bibliothèque législative.

## Installation du catalogue (sans toucher aux bases déjà installées)

Exécuter dans apps/symfony, depuis une installation synchronisée avec main :

~~~bash
mkdir -p var
curl -fLsS --retry 3 \
  'https://www.data.gouv.fr/api/1/datasets/r/7698274a-65f6-4687-8b2b-6961199b6922' \
  -o /tmp/populorum-propositions-senat.csv

python3 tools/import_policy_catalog.py \
  --senat-csv /tmp/populorum-propositions-senat.csv \
  --limit 200 --days 90
~~~

Le script **refuse de démarrer sans fichier CSV officiel**. Il n'intègre
aucune mesure de programme ni proposition inventée pour atteindre le quota.

La valeur `database_total` correspond au nombre de propositions de loi
officielles présentes, **pas** au nombre total de lignes historiques ;
`inserted` indique les nouveaux dossiers ajoutés.
Pour augmenter l'objectif, reprendre avec `--limit 1000`.

L'import est idempotent, ne réinitialise pas les consultations, préserve les
votes et utilise le fichier indépendant `var/policy-catalog.sqlite`.
Pour éviter les faux positifs, l'importeur n'accepte que les URLs de dossiers
du Sénat, en HTTPS, et que les types « proposition de loi ». Il exclut
les statuts non poursuivis dans le même dossier et les textes promulgués
selon l'état de l'export téléchargé.

Un contrôle éditorial reste nécessaire : titres très similaires, évolutions
de statuts et contenu exact des articles ne sont pas déduits des métadonnées.
Deux propositions portant le même intitulé mais déposées dans des dossiers
distincts restent identifiables séparément par leur URL officielle. Un
rapprochement juridique détaillé reste nécessaire pour identifier les vrais
doublons de contenu.

## Participation

- /mesures : consultation et recherche paginée de dossiers officiels.
- /mesures/{id} : vote fictif sur le principe d'une proposition de loi,
  Pour / Contre / Abstention.
- Seuls les comptes de test peuvent enregistrer un bulletin.
- Une position active par compte de test et par dossier, avec possibilité de
  révision jusqu'à clôture ; historique des révisions.
- Les votes des comptes liés à une IP ne garantissent ni personne unique,
  ni échantillonnage représentatif ; aucun effet juridique.
- L'attribution est dévoilée après clôture, avec le résultat agrégé.
- Les consultations historiques du moteur civique original ne sont pas
  réécrites ni converties.

## Contrôles techniques

~~~bash
python3 tests/policy_catalog_import_test.py
php vendor/bin/phpunit tests/PolicyCatalogTest.php
php bin/console lint:twig templates --env=demo
~~~

Seuls des dossiers issus de sources publiques vérifiées peuvent être présentés.
