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

Le fichier SQLite dérivé `data/geography/communes-2026.sqlite` est un index géographique **distinct** de la base métier Doctrine et de l'index financier OFGL. Il est construit localement et peut être régénéré par le même script, avec contrôle minimal du nombre de communes et remplacement atomique :

```bash
cd apps/symfony
python3 tools/import_communes_insee.py --download
```

En cas d'échec réseau, télécharger l'archive `cog_ensemble_2026_csv.zip` sur la page de l'INSEE, puis :

```bash
python3 tools/import_communes_insee.py --zip ~/Téléchargements/cog_ensemble_2026_csv.zip
```

Ou fournir les 3 CSV extraits par `--communes`, `--departements` et `--regions` (voir `--help`). Vérifier :

```bash
python3 tools/verify_geography.py
php bin/console debug:router site_communes
php bin/console debug:router budget_city_search
```

Ne pas ajouter le fichier SQLite produit par l'import dans Git sans décision explicite sur les données dérivées. L'index ne contient pas de données nominatives.

## Séparation des périmètres

- **COG 2026** : noms des communes, départements, régions, code INSEE. L'existence d'une commune ne présume pas de ses chiffres financiers.
- **OFGL 2025** : montants comptables consolidés des communes effectivement importées dans `BudgetCatalog`. L'index national financier est construit séparément avec `tools/import_budget_national.py` ; il n'est pas généré à partir du COG.
- **DGFiP** : balances détaillées actuellement archivées pour les trois pilotes uniquement.
- **Simulateur** : trois villes pilotes avec citoyens et votes fictifs. Les autres fiches géographiques sont en lecture seule, sans population, consultation, scrutin ou coût fictif généré automatiquement.

Dans l'arbre financier, une commune dépourvue de comptes importés affiche explicitement **données financières non chargées** : `null` n'est jamais transformé en zéro. Une chaîne de paiements n'est tracée que lorsqu'il existe un justificatif correspondant.

## Performance et sécurité

Requêtes SQL paramétrées, validation stricte du code INSEE, index SQLite en lecture seule (`query_only=ON`), filtres et pagination sur 40 communes, absence de chargement de ~35 000 résultats dans le HTML initial. Les tables géographiques ne sont pas créées dans Doctrine ; il n'y a aucune migration de la base des simulations. Les erreurs de téléchargement ne remplacent pas la base géographique précédente.

## Évolution ultérieure

Pour couvrir les budgets de toutes les communes, charger séparément l'extrait national OFGL 2025. Mettre en place ensuite les jeux d'open data des subventions, marchés publics et comptabilités locales en conservant chaque pièce source, son millésime et le niveau de preuve. Ne jamais déduire la destination finale d'une subvention de la seule nationalité du bénéficiaire.
