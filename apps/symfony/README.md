# Démos Kratos — Symfony 8.1 et SQLite

Application native **PHP 8.4 / Symfony 8.1 / Doctrine / Twig / Symfony Form et Validator**, sans dépendance React, Node.js ni service JavaScript côté serveur. La base est un fichier SQLite : `var/populorum.sqlite`.

Le projet reste une simulation fictive. **Les 34 875 communes du COG INSEE 2026** sont synchronisées dans Doctrine à l’installation. Un espace de simulation vide et indépendant peut être créé pour toute commune sélectionnée. La sélection géographique n’invente ni votes, ni habitants, ni résultats. Un ancien jeu de démonstration de 300 personnages et de 27 propositions fictives est conservé **uniquement pour compatibilité avec les scénarios historiques non sélectionnés**. Les nouveaux espaces communaux démarrent vides, sans habitants ni propositions fictives préremplis. Les projets s’appuient sur des sources publiques datées, distinctes des hypothèses et résultats inventés. Voir [recherche et fixtures](docs/MUNICIPAL_FIXTURES.md).

## Installation — 28 locales

Choisissez votre langue pour lire le guide d’installation et de mise à jour : prérequis, démarrage local, mises à jour et dépannage.

| Langue | Guide d’installation |
|---|---|
| 🇫🇷 Français | [`fr_FR`](docs/translations/fr_FR/INSTALLATION.md) |
| 🇬🇧 English (UK) | [`en_GB`](docs/translations/en_GB/INSTALLATION.md) |
| 🇺🇸 English (US) | [`en_US`](docs/translations/en_US/INSTALLATION.md) |
| 🇨🇦 English (Canada) | [`en_CA`](docs/translations/en_CA/INSTALLATION.md) |
| 🇮🇪 English (Ireland) | [`en_IE`](docs/translations/en_IE/INSTALLATION.md) |
| 🇪🇸 Español | [`es_ES`](docs/translations/es_ES/INSTALLATION.md) |
| 🇩🇪 Deutsch | [`de_DE`](docs/translations/de_DE/INSTALLATION.md) |
| 🇮🇹 Italiano | [`it_IT`](docs/translations/it_IT/INSTALLATION.md) |
| 🇵🇹 Português | [`pt_PT`](docs/translations/pt_PT/INSTALLATION.md) |
| 🇳🇱 Nederlands | [`nl_NL`](docs/translations/nl_NL/INSTALLATION.md) |
| 🇵🇱 Polski | [`pl_PL`](docs/translations/pl_PL/INSTALLATION.md) |
| 🇨🇿 Čeština | [`cs_CZ`](docs/translations/cs_CZ/INSTALLATION.md) |
| 🇸🇰 Slovenčina | [`sk_SK`](docs/translations/sk_SK/INSTALLATION.md) |
| 🇭🇺 Magyar | [`hu_HU`](docs/translations/hu_HU/INSTALLATION.md) |
| 🇭🇷 Hrvatski | [`hr_HR`](docs/translations/hr_HR/INSTALLATION.md) |
| 🇷🇴 Română | [`ro_RO`](docs/translations/ro_RO/INSTALLATION.md) |
| 🇬🇷 Ελληνικά | [`el_GR`](docs/translations/el_GR/INSTALLATION.md) |
| 🇷🇺 Русский | [`ru_RU`](docs/translations/ru_RU/INSTALLATION.md) |
| 🇹🇷 Türkçe | [`tr_TR`](docs/translations/tr_TR/INSTALLATION.md) |
| 🇸🇦 العربية | [`ar_SA`](docs/translations/ar_SA/INSTALLATION.md) |
| 🇮🇳 हिन्दी | [`hi_IN`](docs/translations/hi_IN/INSTALLATION.md) |
| 🇯🇵 日本語 | [`ja_JP`](docs/translations/ja_JP/INSTALLATION.md) |
| 🇨🇳 简体中文 | [`zh_CN`](docs/translations/zh_CN/INSTALLATION.md) |
| 🇮🇩 Bahasa Indonesia | [`id_ID`](docs/translations/id_ID/INSTALLATION.md) |
| 🇲🇾 Bahasa Melayu | [`ms_MY`](docs/translations/ms_MY/INSTALLATION.md) |
| 🇻🇳 Tiếng Việt | [`vi_VN`](docs/translations/vi_VN/INSTALLATION.md) |
| 🇬🇪 ქართული | [`ka_GE`](docs/translations/ka_GE/INSTALLATION.md) |
| 🇲🇳 Монгол | [`mn_MN`](docs/translations/mn_MN/INSTALLATION.md) |

## Installation et démarrage — commandes explicites

Suivre le **[guide complet de la racine du dépôt](../../LIRE_AVANT_INSTALLATION.md)** : installation des paquets (pacman sur Arch, gestionnaire équivalent sur les autres systèmes), activation des extensions PHP, `composer install`, génération locale des clés, migrations et validation HTTP.

Depuis `apps/symfony`, avec PHP CLI ≥ 8.4 et les extensions chargées :

```bash
composer install --no-interaction --prefer-dist --no-progress &&
test -f vendor/autoload.php &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo --no-debug &&
php bin/console doctrine:schema:validate --env=demo &&
python3 tests/http_smoke.py
```

Les commandes s'exécutent depuis `apps/symfony`. Le référentiel national est restauré par PHP/Symfony. Le contrôle `tests/http_smoke.py` lance un vrai serveur avec **une copie temporaire de la base**.

```bash
APP_ENV=demo APP_DEBUG=0 php -S 127.0.0.1:5412 -t public
```

**Bases nationales :** installer et vérifier INSEE/La Poste via `php bin/console app:geography:install --env=demo` et `php bin/console app:geography:install --check --env=demo` (la préparation de la démo effectue aussi la restauration automatique), puis suivre [installation des données nationales](docs/INSTALLATION_DONNEES_NATIONALES.md) pour la base DECP indépendante (archive unique compressée d'environ 464 Mio, vérifiée par SHA-256). La base nationale de marchés est distincte de `var/populorum.sqlite`.

## Le fichier SQLite demandé

Deux fichiers versionnés conservent un instantané minimal de l'ancienne démonstration pédagogique. **Ils ne remplacent pas le référentiel national INSEE**, restauré depuis les sources lors de l'installation :

- `data/populorum-demo.sqlite` : base SQLite prête à copier, sans simulation de visiteur ni clé privée.
- `data/populorum-demo.sql` : export SQL lisible et reproductible du même état.

L’installation par migrations est recommandée. Pour utiliser plutôt la base prête à l’emploi, sur une installation neuve uniquement, **avant toute utilisation** :

```bash
mkdir -p var
cp data/populorum-demo.sqlite var/populorum.sqlite
php bin/console app:geography:install --env=demo
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
