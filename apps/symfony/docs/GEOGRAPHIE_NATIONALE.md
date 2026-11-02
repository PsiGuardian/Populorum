# Référentiel territorial unique — INSEE COG 2026

## Un seul catalogue, plusieurs usages

La géographie est fournie par `App\Service\GeographyCatalog` :

- annuaire public `/communes` et fiches `/communes/{code}` ;
- champ de recherche commun au site (`templates/partials/geography-search.html.twig`) ;
- suggestion de commune du budget (réutilise `BudgetCatalog::search()`, qui délègue à `GeographyCatalog`) ;
- parcours régional/départemental/communal de l'arbre financier (`BudgetTree` via `BudgetCatalog`) ;
- lien entre la commune sélectionnée et les budgets disponibles (code INSEE).

Ne **pas** ajouter de seconde liste de communes dans `DemoController`, les fixtures ou un template. Le code INSEE est l'identifiant géographique canonique (ne pas confondre avec le code postal). Pour une version historique, tenir compte des changements de codes entre 2025 et 2026.

## Origine, licence et mise à jour

Source : INSEE, *Code officiel géographique au 1er janvier 2026*, [documentation officielle](https://www.insee.fr/fr/information/8740222) (licence ouverte de la donnée publique). Le constructeur retient `TYPECOM=COM`, afin de ne pas compter les arrondissements municipaux ou communes déléguées comme des communes distinctes. La couverture correspond aux communes du COG France métropolitaine et départements/régions d'outre-mer ; les collectivités ultramarines organisées différemment ne sont pas assimilées à des communes françaises.

Le référentiel COG 2026 et les correspondances postales La Poste sont fournis dans
`data/geography/communes-2026.sqlite.gz`. L'application restaure le fichier SQLite
`data/geography/communes-2026.sqlite` hors réseau, sans écraser une base existante :

```bash
cd apps/symfony
php bin/console app:geography:install --env=demo
php bin/console app:geography:install --check --env=demo
php bin/console app:demo:prepare --env=demo
```

La préparation de la démo déclenche également l'installation si l'index est absent.
L'installateur PHP vérifie l'empreinte SHA-256, l'intégrité SQLite, le millésime et
les tables `communes` / `postal_codes`. Les données géographiques restent en
dehors de Git et du dossier `public/`.

Une copie SQLite indépendante est disponible sur
[GitHub Releases — data-national-2026](https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026).

La table Doctrine `municipalities` est synchronisée à partir du même référentiel
sans créer d'habitants, de votes ou de financements fictifs.

## Séparation des périmètres

- **COG 2026** : noms des communes, départements, régions, code INSEE. L'existence d'une commune ne présume pas de ses chiffres financiers.
- **OFGL 2025** : montants comptables consolidés des communes effectivement importées dans `BudgetCatalog`. L'index national financier est construit séparément avec `tools/import_budget_national.py` ; il n'est pas généré à partir du COG.
- **DGFiP** : balances détaillées limitées aux communes et aux millésimes réellement importés ; aucune substitution géographique.
- **Simulateur** : espace fictif indépendant et initialement vide pour chaque commune sélectionnée, sans habitant, vote ni coût inventé.

Dans l'arbre financier, une commune dépourvue de comptes importés affiche explicitement **données financières non chargées** : `null` n'est jamais transformé en zéro. Une chaîne de paiements n'est tracée que lorsqu'il existe un justificatif correspondant.

## Performance et sécurité

Requêtes SQL paramétrées, validation stricte du code INSEE, index SQLite en lecture seule (`query_only=ON`), filtres et pagination sur 40 communes, absence de chargement de ~35 000 résultats dans le HTML initial. Les tables détaillées de géographie restent séparées de Doctrine ; seule la table applicative `municipalities` est synchronisée par la commande de préparation. Les erreurs de téléchargement ne remplacent pas la base géographique précédente.

## Évolution ultérieure

Pour couvrir les budgets de toutes les communes, charger séparément l'extrait national OFGL 2025. Mettre en place ensuite les jeux d'open data des subventions, marchés publics et comptabilités locales en conservant chaque pièce source, son millésime et le niveau de preuve. Ne jamais déduire la destination finale d'une subvention de la seule nationalité du bénéficiaire.
