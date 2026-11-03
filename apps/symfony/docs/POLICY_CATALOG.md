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
- Les anciennes propositions fictives sont remplacées par les rubriques officielles /chemins (bibliothèque) et /consultations (vote et suivi). L’URL historique /proposals redirige vers /mesures.
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

## Délibération causale après le vote

Le bulletin reste un choix libre entre Pour, Contre et Abstention. Pour
argumenter publiquement, le citoyen doit **d'abord enregistrer son vote**
et compléter ensuite un **couple causal obligatoire** dans l'arbre de
la fiche officielle /mesures/{id} :

- Cause (25–1 500 caractères), degré de preuve : probable / observée /
  présentée comme démontrée, URL HTTPS si possible ;
- Conséquence (25–1 500 caractères), degré de preuve identique,
  URL HTTPS si possible ;
- Si une cause ou une conséquence est présentée comme « démontrée »,
  une URL HTTPS est obligatoire. La présence d'une URL ne constitue pas une
  vérification par l'application ni une preuve scientifique ;
- Réponse possible à une branche antérieure du même dossier (six niveaux
  supplémentaires maximum), sans champ de commentaire libre. Les corrections
  prennent la forme de nouvelles branches plutôt que de réécritures.
- Le vote d'origine n'est jamais modifié par la contribution. Les branches
  restent ajoutables **après la clôture**, à condition que le compte actif
  ait participé au scrutin de simulation.

Pendant que le scrutin est ouvert, seuls les profils de test ayant déjà voté
peuvent lire et compléter son arbre, pour éviter d'influencer les visiteurs
n'ayant pas encore pris position. Après clôture, l'arbre devient public,
même pour ceux qui n'ont pas participé.

Les branches n'affichent qu'un libellé de compte de test pseudonyme, jamais
l'adresse IP, son empreinte ni le prénom/nom déclarés. Les nouveaux argumentaires
libres sont refusés côté serveur, tandis que les anciens restent conservés
avec les votes préexistants. Toutes les branches sont historiques et
persistent après suppression du profil, sans données de nom.

Stockage : table `policy_deliberations` de `var/policy-catalog.sqlite`.
Création additive via l'importeur ou au premier accès à l'arbre ; aucun
changement des anciens bulletins ou de la base INSEE/DECP.
Les branchements ne modifient pas le statut parlementaire d'une proposition.

Limites : aucune modération éditoriale humaine ni vérification automatique
du sens du texte n'est assurée. Les classifications « probable »,
« observée » et « démontrée » sont déclaratives ; un lien ne garantit
ni crédibilité ni lien causal.

## Contrôles techniques

~~~bash
python3 tests/policy_catalog_import_test.py
php vendor/bin/phpunit tests/PolicyCatalogTest.php
php bin/console lint:twig templates --env=demo
~~~

Seuls des dossiers issus de sources publiques vérifiées peuvent être présentés.
