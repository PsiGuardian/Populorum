# Catalogue des contournements et des surfaces SQL — Populorum

Cet inventaire concerne uniquement des essais autorisés et des bases SQLite temporaires. Il ne certifie pas l'absence d'injection SQL.

## Charges de test

| Catégorie | Exemple d'entrée de test | Contrôle attendu |
|---|---|---|
| Tautologie | `' OR 1=1 --` | Ne pas élargir un filtre de périmètre autorisé |
| Requête empilée | `'; DROP TABLE contracts; --` | Ne pas modifier la base, même si le champ est malveillant |
| UNION | `' UNION SELECT name FROM sqlite_master --` | Ne pas extraire de données hors du résultat normal |
| Commentaires SQL | `x' /* comment */` | Ne pas changer la grammaire SQL |
| Guillemets | `" OR "x"="x` | Valeurs liées, jamais concaténées |
| FTS5 / opérateurs | `") OR (1=1` | Termes de recherche traités comme texte |
| LIKE | `%_!` | Wildcards échappés lorsque la recherche est littérale |
| Caractères spéciaux | retours ligne, octets NUL | Traitement sans erreur ni modification |
| Pagination | offset négatif, limite excessive | Bornes contrôlées côté serveur |
| Identifiant de colonne | booléen includeVotes | Choix entre constantes internes, jamais un nom fourni par HTTP |

## Surfaces et champs

| Service | Entrées vers SQL | Protection présente | Vérification automatisée |
|---|---|---|---|
| PublicProcurementData.search | kind, year, buyer, supplier, city, department, q, offset, limit | Clauses SQL constantes, valeurs liées, pagination bornée, SQLite lecture seule | Oui : charges adversariales sur chaque champ |
| PublicProcurementData.detail | key | clé hexadécimale exacte, SQL lié | Oui |
| PublicProcurementData.cityCount / years | insee, kind | Validation et liaison PDO | Oui |
| PublicProcurementData.budgetConcordance | scope, accountingYear | Paramètres liés, FR ou commune exacte | Oui, y compris régression syntaxique WHERE |
| GeographyCatalog.find / search | code, term, offset, limit, terme INSEE, code postal | SQL lié, LIKE échappé, lecture seule | Oui |
| GeographyCatalog.departments / cityPage | region, department, offset, limit | Entrées validées, SQL lié | Oui |
| BudgetCatalog.city | code commune | SQL lié, lecture seule | Oui |
| PublicBudget | siren, préfixe compte, page | SQL lié / query_only | À compléter par fixture de balances |
| DemoDatabase | owner, revision, state, includeVotes | DBAL, révision optimiste, colonne conditionnelle issue d'un booléen interne | Audit des autorisations et des écritures à poursuivre |
| Importeurs Python DECP | champs JSON, identifiants, dates, chemins fichiers | sqlite3 préparé sur les données, logique UPSERT | Tests d'import existants ; revue injection dynamique à poursuivre |
| Doctrine et entités | formulaires, scénarios, votes, associations | ORM et contrôles applicatifs | Tests d'isolation et de droits métier à poursuivre |

## Résultats et limites

- Les fichiers de test sont créés sous un répertoire temporaire, sans connexion aux 725 813 contrats réels et sans modification du SQLite de simulation.
- Contrôle SHA-256 avant/après pour l'ensemble des bases échantillons en lecture seule.
- La recherche publique DECP ignore actuellement certains filtres invalides et peut alors afficher une liste plus large de données déjà publiques : c'est un problème de validation métier distinct d'une injection SQL.
- Les surfaces de mutation Doctrine ne sont pas garanties par les tests de catalogues en lecture seule ; ne pas conclure à une sécurité globale.
- L'inventaire des appels SQL est générable par la commande python3 tools/sql_query_inventory.py --json ; chaque emplacement doit être interprété manuellement.

## Exécution

Depuis apps/symfony :

    php vendor/bin/phpunit tests/SqlInputSecurityTest.php
    python3 tools/sql_query_inventory.py --json > var/sql-surface-inventory.json
    composer test

Ne jamais exécuter de tests destructeurs sur une copie de la base nationale ni sur le site public.
