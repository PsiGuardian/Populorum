# Populorum — archive de revue de l’interface

Cette archive complète est préparée pour validation avant publication sur GitHub. Elle conserve Symfony, Doctrine, Twig et SQLite, les 300 personnages fictifs, les 27 propositions et leurs simulations. Les dépendances PHP `vendor/` sont incluses. Aucun serveur public n’a été modifié.

## Démarrer une installation neuve

Il faut PHP 8.4 ou supérieur et les extensions mentionnées dans `apps/symfony/README.md`.

```bash
cd apps/symfony
php tools/init-demo.php
php bin/console doctrine:migrations:migrate --no-interaction --env=demo
php bin/console app:demo:prepare --env=demo
php bin/console cache:clear --env=demo
php -S 127.0.0.1:5412 -t public
```

Ouvrir http://127.0.0.1:5412/ puis « Comparer aux observations ». Pour le profiler, les cookies sécurisés requièrent HTTPS : consulter le guide d’installation et `profiler-shield.md`. Les dépendances peuvent être régénérées avec `composer install`, à partir de `composer.lock`.

## Mettre à jour une installation existante

Sauvegarder d’abord ses fichiers et sa base active. Copier les sources et dépendances de l’archive en conservant ses propres `.env.local`, `.guardian-shield/` et `var/`. Vider le cache Symfony après copie. Ne jamais remplacer une base active par la base de démonstration fournie dans `data/`.

Les clés privées, sessions, bases de visiteurs, caches et journaux d’exécution sont volontairement exclus. `data/populorum-demo.sqlite` est une base initiale de référence ; `var/populorum.sqlite` sera créé localement. Aucune clé d’instance n’est partagée dans cette archive.

## Ce qui change

- Arbre de cause à effet ouvert et central, avec lecture textuelle accessible.
- Navigation par actions, formulaires regroupés et comparaisons paginées.
- Données publiques SSMSI 2024–2025 distinctes des expériences fictives ; quinze indicateurs sélectionnables et comparaison de leurs évolutions.
- Mention explicite d’un projet complémentaire à un système d’État déjà existant.

Lire `apps/symfony/docs/INTERFACE_REVIEW.md` pour les décisions, les risques, les limites et les vérifications. Toutes les données publiques sont datées ; aucune actualisation automatique ni efficacité réelle des propositions n’est revendiquée.

## Recherche et récits progressifs — nouveau lot

Cette archive ajoute les routes personnalisées, la validation de recherche, les récits fictifs avec ajouts successifs et incertitudes, les catégories statistiques détaillées et les noms fictifs des 300 personnages/familles. Lire `apps/symfony/docs/SEARCH_AND_CAUSAL_ACCOUNTS.md` pour le fonctionnement, les garanties vérifiées et les limites juridiques.

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
php bin/console doctrine:migrations:migrate --no-interaction --env=demo
php bin/console cache:clear --env=demo
```

La migration additive `Version20261030150000` ajoute la vue légère de lecture et son invalidation ; elle ne remplace ni les snapshots, ni les votes. Les laboratoires existants reconstruisent leur vue au premier accès. Le rapport explique le protocole, ses limites et les commandes de reproduction. Aucun réglage système ou contrôle de sécurité n’a été désactivé pour obtenir ces gains.

## Lot « Montée en charge ×5 » — migration nécessaire

Lire `apps/symfony/docs/PERFORMANCE_SCALE_REPORT.md` et son tableau HTML/CSV : essais à 1 500 citoyens fictifs, 135 consultations et 13 500 positions, puis cinq laboratoires indépendants. Les listes sont paginées, les décomptes sont limités aux consultations visibles et la navigation dispose d’une vue sans bulletins inutilisés. Les écritures relationnelles deviennent incrémentales ; la preuve et les historiques restent complets.

La migration additive `Version20261030180000` ajoute `overview_state` et son invalidation. **Conserver votre base et vos clés**, exécuter les migrations puis vider le cache avec les commandes du lot précédent. Les exports, vérifications et mutations complètes conservent un coût lié à la taille du snapshot ; le rapport ne promet ni attente imperceptible ni capacité de production multiutilisateur déjà validée. Aucune publication GitHub.
