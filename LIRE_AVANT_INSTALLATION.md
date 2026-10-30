# Populorum — installation et mise à jour

## Prérequis

Il faut **PHP 8.4 ou supérieur**, **Composer** et les extensions indiquées dans [le guide Symfony](apps/symfony/README.md#installation-sur-arch-linux). Sur Arch Linux, les paquets système s’installent avec `sudo pacman -S --needed php php-sqlite composer` ; le guide précise les extensions à vérifier.

L’archive téléchargée depuis GitHub contient les sources et `composer.lock`, mais **ne contient pas le dossier `vendor/`**. Il faut installer les dépendances avec Composer avant toute commande `php bin/console`. `composer install` utilise les versions figées dans `composer.lock` ; ne pas le remplacer par `composer update` pour une installation normale.

## Démarrer une installation neuve

Extraire l’archive, ouvrir un terminal à la racine du dossier extrait (`Populorum-main` pour l’archive de la branche `main`), puis exécuter :

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Si le terminal est déjà dans `apps/symfony`, commencer directement par `composer install`. Les `&&` arrêtent la séquence dès qu’une étape échoue. Corriger l’erreur affichée avant de continuer ; ne pas démarrer le serveur si l’installation ou la préparation a échoué.

Ouvrir [http://127.0.0.1:5412/](http://127.0.0.1:5412/). Le serveur garde le terminal occupé ; **Ctrl+C** l’arrête. Ce serveur PHP sert aux essais locaux. Pour une mise en ligne, suivre [le guide Symfony](apps/symfony/README.md#mise-en-ligne-de-linstance-de-démo).

`tools/init-demo.php` génère les clés locales dans `.env.local` seulement si ce fichier n’existe pas. Les clés privées, sessions, bases de visiteurs, caches et journaux d’exécution sont exclus du dépôt. `data/populorum-demo.sqlite` est une base initiale de référence ; la base active `var/populorum.sqlite` est créée localement.

Pour le profiler, les cookies sécurisés requièrent HTTPS, y compris lors d’un essai manuel local : consulter [le guide Symfony](apps/symfony/README.md) et [profiler-shield.md](profiler-shield.md).

### Si `vendor/autoload.php` est introuvable

Les dépendances n’ont pas été installées, ou leur installation n’a pas abouti. Arrêter le serveur avec **Ctrl+C**, exécuter `composer install --no-interaction --prefer-dist` depuis `apps/symfony`, puis reprendre la préparation après sa réussite. Si Composer signale une version de PHP ou une extension manquante, corriger ce prérequis ; ne pas contourner le contrôle avec `--ignore-platform-reqs`.

## Mettre à jour une installation existante

Arrêter le serveur local avec **Ctrl+C**, puis sauvegarder les fichiers, la base active et les clés. Mettre à jour les sources en conservant ses propres `.env.local`, `.guardian-shield/` et `var/`. Ne jamais remplacer une base active par la base de démonstration fournie dans `data/`.

Depuis `apps/symfony`, installer les dépendances correspondant au nouveau fichier de verrouillage, appliquer les migrations, préparer les données puis vider le cache :

```bash
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Le script d’initialisation conserve un `.env.local` existant. Après réussite de toutes les étapes, relancer le serveur local :

```bash
php -S 127.0.0.1:5412 -t public
```

## Ce qui change

- Arbre de cause à effet ouvert et central, avec lecture textuelle accessible.
- Navigation par actions, formulaires regroupés et comparaisons paginées.
- Données publiques SSMSI 2024–2025 distinctes des expériences fictives ; quinze indicateurs sélectionnables et comparaison de leurs évolutions.
- Mention explicite d’un projet complémentaire à un système d’État déjà existant.

Lire `apps/symfony/docs/INTERFACE_REVIEW.md` pour les décisions, les risques, les limites et les vérifications. Toutes les données publiques sont datées ; aucune actualisation automatique ni efficacité réelle des propositions n’est revendiquée.

## Recherche et récits progressifs — nouveau lot

Le projet propose les routes personnalisées, la validation de recherche, les récits fictifs avec ajouts successifs et incertitudes, les catégories statistiques détaillées et les noms fictifs des 300 personnages/familles. Lire `apps/symfony/docs/SEARCH_AND_CAUSAL_ACCOUNTS.md` pour le fonctionnement, les garanties vérifiées et les limites juridiques.

Après mise à jour, vider le cache Symfony. Pour voir les nouvelles fixtures sans écraser les anciennes : **Participer → Ouvrir un nouveau laboratoire**.

## Services, compétences, missions et budget public

Les nouvelles entrées sont **Services et missions** et **Budget et priorités**. Ouvrir un nouveau laboratoire depuis **Participer** charge les 300 profils professionnels fictifs. Le budget public 2025 est disponible indépendamment des anciennes simulations.

Le module coordonne des propositions volontaires dans la fiction ; il ne dépêche aucun intervenant réel. Les données OFGL/DGFiP sont réelles, datées et distinctes des coûts fictifs de mission. La cible de 1 €/habitant/an est un objectif de scénario, non un résultat fiscal atteint. Voir `apps/symfony/docs/CITY_SERVICES_AND_BUDGET.md` pour les périmètres, sources, calculs et limites.

## Lot « Besoins et devis »

La nouvelle entrée **Besoins et devis** (`/besoins-et-devis`) permet de publier un besoin fictif, déposer des offres chiffrées jusqu’à l’ouverture après échéance, poser des questions communes, examiner les offres en tiers indépendant, motiver un choix, suivre les avenants, déclarer le résultat, le contrôler et enregistrer un paiement fictif. Une alternative habitante volontaire peut être préparée pour des tâches simples à faible risque, avec coût complet et solution de repli.

Lire `apps/symfony/docs/PUBLIC_PROCUREMENT.md` pour les formats, droits du scénario, calculs et limites. Ce module ne constitue pas une plateforme réglementaire de commande publique ; toutes ses entreprises et pièces sont fictives. Les anciennes données restent compatibles : la collection de dossiers est créée lors de la première action. Ne pas remplacer votre base ou vos clés par celles d’un autre laboratoire.

## Lot « Performances » — migration nécessaire

Le rapport complet est dans `apps/symfony/docs/PERFORMANCE_REPORT.md`, avec un tableau HTML filtrable, un CSV des traitements par page et les JSON de mesure. La nouvelle interface remplace le flux continu des consultations par des requêtes courtes, allège les listes et les lectures courantes, conserve les historiques et les contrôles complets.

**Sur une installation existante, conserver `var/`, `.env.local` et toutes les clés, puis exécuter depuis `apps/symfony` :**

```bash
composer install --no-interaction --prefer-dist &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console cache:clear --env=demo
```

La migration additive `Version20261030150000` ajoute la vue légère de lecture et son invalidation ; elle ne remplace ni les snapshots, ni les votes. Les laboratoires existants reconstruisent leur vue au premier accès. Le rapport explique le protocole, ses limites et les commandes de reproduction. Aucun réglage système ou contrôle de sécurité n’a été désactivé pour obtenir ces gains.

## Lot « Montée en charge ×5 » — migration nécessaire

Lire `apps/symfony/docs/PERFORMANCE_SCALE_REPORT.md` et son tableau HTML/CSV : essais à 1 500 citoyens fictifs, 135 consultations et 13 500 positions, puis cinq laboratoires indépendants. Les listes sont paginées, les décomptes sont limités aux consultations visibles et la navigation dispose d’une vue sans bulletins inutilisés. Les écritures relationnelles deviennent incrémentales ; la preuve et les historiques restent complets.

La migration additive `Version20261030180000` ajoute `overview_state` et son invalidation. **Conserver votre base et vos clés**, exécuter les migrations puis vider le cache avec les commandes du lot précédent. Les exports, vérifications et mutations complètes conservent un coût lié à la taille du snapshot ; le rapport ne promet ni attente imperceptible ni capacité de production multiutilisateur déjà validée.
