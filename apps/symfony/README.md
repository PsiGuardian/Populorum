# Démos Kratos — Symfony 8.1 et SQLite

Application native **PHP 8.4 / Symfony 8.1 / Doctrine / Twig / Symfony Form et Validator**, sans dépendance React, Node.js ni service JavaScript côté serveur. La base est un fichier SQLite : `var/populorum.sqlite`.

Le projet reste une simulation fictive. Les seules communes initiales sont **Paris (75056), Marseille (13055), Le Havre (76351)**. Leurs noms, rattachements et codes communaux viennent du COG INSEE 2026. Les 300 personnages (100 par ville), les 27 projets expérimentaux (neuf par ville), votes et résultats sont fictifs. Les projets s’appuient sur des sources publiques datées, distinctes des hypothèses et résultats inventés. Voir [recherche et fixtures](docs/MUNICIPAL_FIXTURES.md).

## Installation sur Arch Linux

Les dépendances système proviennent de pacman :

```bash
sudo pacman -S --needed php php-sqlite composer
```

Dans `/etc/php/php.ini`, activer `pdo_sqlite` et `sqlite3`, ainsi que `openssl`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml` et `curl` si ces extensions sont distribuées sous forme de modules sur votre installation. Ne pas ajouter une directive `extension=` pour une extension déjà compilée dans PHP. Vérifier :

```bash
php -v
php -m
```

Il faut PHP 8.4 minimum. Depuis la racine du dépôt :

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist
php tools/init-demo.php
php bin/console doctrine:migrations:migrate --no-interaction --env=demo
php bin/console app:demo:prepare --env=demo
php bin/console cache:clear --env=demo
```

`composer.lock` fige les versions testées. Le bundle est réellement installé depuis la branche **dev-1.0.1** du dépôt demandé, à la révision `3595f1e483e4ea1ee2dcbd6ddbabe7e2976e1783`. La révision est figée par composer.lock ; la contrainte Composer correspondante est `dev-dev-1.0.1` : le premier `dev-` appartient à la notation Composer et le second au nom réel de la branche.

Le script `tools/init-demo.php` génère des clés privées propres à l’instance dans `.env.local`. Le compte public de démo et son chemin sont documentés dans [profiler-shield.md](../../profiler-shield.md). Le compte `operator` généré localement ne peut pas se connecter par HTTP à cette démo publique. La maintenance s’effectue en CLI.

## Le fichier SQLite demandé

Deux fichiers versionnés contiennent uniquement le référentiel public des trois villes et les tables initiales :

- `data/populorum-demo.sqlite` : base SQLite prête à copier, sans simulation de visiteur ni clé privée.
- `data/populorum-demo.sql` : export SQL lisible et reproductible du même état.

L’installation par migrations est recommandée. Pour utiliser plutôt la base prête à l’emploi, sur une installation neuve uniquement, **avant toute utilisation** :

```bash
mkdir -p var
cp data/populorum-demo.sqlite var/populorum.sqlite
php bin/console app:demo:prepare --env=demo
```

Ne jamais remplacer une base d’exécution existante par la base d’exemple. La base active, les sessions, profils, journaux et clés restent hors du dépôt et du dossier `public/`.

## Mise en ligne de l’instance de démo

Le **DocumentRoot doit être `apps/symfony/public/`**, jamais la racine du dépôt ni `apps/symfony/`. Utiliser PHP-FPM avec Nginx ou Apache et HTTPS. Le domaine n’est pas fixé dans le code. Les cookies Guardian sont volontairement `Secure` : sa connexion nécessite HTTPS, y compris lors d’un essai manuel local.

Conserver `APP_ENV=demo`, `APP_DEBUG=0`, `POPULORUM_DEMO=1` pour cette instance isolée. Donner au processus PHP un accès en écriture à `var/` et `.guardian-shield/`, avec permissions restreintes. Ne pas utiliser `chmod 777`.

Le compte public permet de voir les diagnostics pédagogiques, pas de modifier les utilisateurs ou les politiques. PHPInfo, l’ouverture de fichiers et l’intégration Xdebug sont fermés. Les collecteurs publics expurgent les clés d’environnement, cookies, corps de requête et sessions. La collecte des formulaires, des exceptions et du SQL est exclue du profiler public pour éviter de conserver des jetons ou l’état privé des simulations.

Cette instance ne doit recevoir aucun compte civil, secret de production, service de paiement ni donnée réelle confidentielle. Le profiler à identifiants publics est une démonstration, pas une protection de confidentialité des diagnostics.

Exemples de configuration HTTP : `deploy/nginx-demo.conf` et `deploy/apache-demo.conf`. Adapter chemins, domaine, certificats et socket PHP-FPM ; aucun domaine n’a été déployé automatiquement.

## Vérifications exécutables

```bash
composer test
php bin/console lint:twig templates --env=demo
php bin/console lint:yaml config --parse-tags
php bin/console doctrine:schema:validate --env=demo
composer validate --strict
```

`tests/integration.php` utilise le véritable Kernel Symfony, les services installés, Twig, les formulaires CSRF, Doctrine et une copie temporaire du SQLite fourni. Il vérifie aussi la connexion réelle du bundle et le passage de « profiler refusé » à « profiler accessible » après connexion. Les contrôles ne sont pas des tests du démonstrateur Sites.

## Fonctionnalités de cette première base

Parcours natifs rendus côté serveur : accueil, communes, chemins et douze catégories, brouillons/publication/révisions, consultations et versions figées, admissibilité et consentement, trois choix de vote, modification sans double comptage, clôture, observations sourcées, protocoles et bilans, comparaison thématique, mémoire, exports, journal SHA-256 signé ECDSA, vérificateur PHP autonome, finances fictives, contributions, gouvernance et recours, relais virtuels et charte dans les 28 locales documentaires.

Les formulaires fonctionnent sans JavaScript. La mise à jour des résultats se fait par rechargement ; SSE/Mercure reste à intégrer. Les opérations sont protégées par CSRF, identifiants anti-rejeu et une écriture SQLite atomique conditionnée à la révision courante. Chaque session de navigateur possède son propre scénario fictif. Les identités Guardian sont indépendantes des personnages de simulation et ne donnent pas une voix supplémentaire.

La première modélisation Doctrine comprend `Municipality` et `Simulation`. Le contenu de chaque scénario est conservé comme document JSON transactionnel dans SQLite ; la normalisation de toutes les entités métier du cahier des charges reste à développer. Aucun réseau distribué réel ni audit national n’est revendiqué.

## Vérification indépendante hors ligne

```bash
php tools/verify-export.php populorum-proofs.json
php tools/recount.php populorum-consultation.json
```

Les signatures sont celles de l’instance, pas des preuves d’identité civile. Un opérateur possédant sa clé pourrait réécrire l’historique ; une preuve exportée et conservée indépendamment aide à détecter les divergences ultérieures. Le premier jeu de données pédagogique n’est pas signé.

Sources de configuration Arch : [manuel officiel PHP/pacman](https://www.php.net/manual/en/install.unix.pacman.php), [paquet php-sqlite](https://archlinux.org/packages/extra/x86_64/php-sqlite/).

## Lecture et entretien du code

Le [guide des développeurs](docs/CODE_STYLE.md) décrit les responsabilités des fichiers, le trajet d’une action et les conventions de présentation. Pour contrôler ou appliquer le style PHP : `composer style:check` et `composer style:fix`. Ce contrôle fait aussi partie de la CI.

## Couverture du cahier des charges

L’[audit des exigences](docs/REQUIREMENTS_AUDIT.md) compare toutes les rubriques et les 68 scénarios au code, distingue les fonctions partielles et contient les preuves des écarts reproduits. Les 99 contrôles de non-régression ne valent pas validation du cahier complet.

Les [sept écarts reproduits ont été corrigés](docs/CORRECTIONS.md), ainsi que le rendu de la charte et les exports d’expériences. `composer test` exécute les 99 contrôles initiaux, 64 contrôles supplémentaires et les 12 sondes désormais satisfaites. Une régression fait échouer la CI. Le résultat avant correction est conservé séparément.

## Fonctionnalités et recette du lot 0.3

Comptes Symfony liés à un personnage, projections SQL transactionnelles, API paginée et SSE, cycles et reformulations versionnées, gouvernance à confirmations indépendantes, modération motivée et recours, convergence descriptive, bilans territoriaux, sauvegardes chiffrées et réplication HTTPS avec agent volontaire sont implémentés. Les commandes d’exploitation et limites sont dans [OPERATIONS.md](docs/OPERATIONS.md), le contrat HTTP dans [API.md](docs/API.md), les vérifications dans [COMPLETION_REVIEW.md](docs/COMPLETION_REVIEW.md).

```bash
composer test
composer analyse
composer style:check
php bin/console doctrine:schema:validate --env=demo
```

Les scénarios historiques à neuf personnages sont conservés dans `data/reference-scenario.json` pour les régressions. Les nouveaux laboratoires utilisent `data/scenario.json.gz` (JSON complet compressé sans perte). Un laboratoire déjà créé ne change jamais de population ou de texte voté au chargement d’une mise à jour.

## Personnages évolutifs — lot 0.4

Chaque personnage a un portrait, des priorités, une manière de s’exprimer et une réaction propre aux erreurs et aux retours d’expérience fictifs. Les 300 personnages votent sur les neuf projets de leur ville : 2 700 votes, chacun accompagné de douze réponses, soit 32 400 réponses courantes. Trois tours sont conservés ; les suivants se déclenchent depuis Participer avec un personnage de gouvernance fictive. Les votes clos et les choix manuels sont préservés.

Voir [le modèle narratif, les archives et les tests](docs/FICTIONAL_MINDS.md). Ouvrir un nouveau laboratoire pour charger ce millésime ; une session existante ne change pas de données silencieusement. Les choix ne représentent pas les opinions des habitants réels.

## Suffrage familial — lot 0.5

Chaque personnage conserve son opinion et ses douze réponses. Pour l’adoption, chaque famille fictive déclarée compte au maximum une voix, quelle que soit sa taille. Le résultat familial et les opinions individuelles sont affichés séparément. Majorité des POUR/CONTRE au sein de la famille ; égalité ou seules abstentions = abstention familiale ; quorum calculé sur les familles. Rattachements et règle sont figés à l’ouverture. Voir [la règle, ses exemples et ses tests](docs/FAMILY_SUFFRAGE.md).

La charte 1.1 et ses 28 versions documentaires portent l’amendement. Les anciennes consultations gardent leurs règles ; ouvrir un nouveau laboratoire pour tester le nouveau millésime.
