# Lire et modifier le code

## Repères

| Fichier | Responsabilité |
| --- | --- |
| `src/Controller/DemoController.php` | Requêtes HTTP, session du personnage, formulaires, rendu et exports. |
| `src/Service/ActionForms.php` | Catalogue explicite des actions : titre, page de retour, types et libellés des champs. |
| `src/Form/SimulationActionType.php` | Construction et validation des formulaires Symfony, avec CSRF. |
| `src/Service/SimulationEngine.php` | Invariants, décompte, signatures et méthodes métier nommées pour chaque action. |
| `src/Service/DemoDatabase.php` | Lecture/écriture via Doctrine DBAL et contrôle de la révision SQLite. |
| `templates/pages/` | Présentation Twig ; la logique d’autorisation et de vote reste en PHP. |
| `templates/partials/macros.html.twig` | Éléments de présentation partagés : badge, lien d’action, carte, filtres. |
| `public/app.css` | Styles non minifiés, regroupés par zones de l’interface et adaptation aux écrans. |
| `tests/integration.php` | Scénarios métier et parcours à travers le véritable Kernel Symfony. |

Pour suivre une mutation, commencer par `DemoController::action()`, puis lire le catalogue `ActionForms` et `SimulationActionType`. `SimulationEngine::apply()` vérifie l’intégrité et l’anti-rejeu, puis délègue à une méthode explicite telle que `castVote()` ou `closeConsultation()`. Le nouvel état et son événement signé sont persistés ensemble par `DemoDatabase::save()`, à condition que la révision soit toujours courante.

## Conventions

- Indentation de quatre espaces pour PHP, Twig et CSS ; deux pour JSON/YAML. `.editorconfig` fixe ces réglages.
- Une instruction par ligne ; accolades et blocs clairement délimités.
- Tableaux structurés et noms de variables métier (`proposal`, `consultation`, `citizen`, `payload`, `state`).
- Des méthodes courtes autant que possible, avec une responsabilité identifiable. Éviter de remettre toutes les actions dans un seul bloc conditionnel.
- Des commentaires pour expliquer un invariant, une limite de confiance ou une décision ; ne pas paraphraser chaque instruction.
- Pas de minification dans les sources suivies par Git. Une éventuelle optimisation des assets doit produire des fichiers de distribution distincts.
- Conserver les clés des données persistées et des exports lors d’un renommage interne. Une modification du format demande une migration et une mise à jour des preuves d’intégrité.

## Vérifier le style PHP

PHP CS Fixer est une dépendance **de développement** de Composer ; aucune dépendance Node.js n’est requise pour cette vérification ni pour exécuter le site.

Depuis `apps/symfony` :

```bash
composer install
composer style:check
composer style:fix
composer test
php bin/console lint:twig templates --env=demo
```

`style:check` est exécuté par GitHub Actions et refuse les écarts. `style:fix` applique la convention Symfony définie dans `.php-cs-fixer.dist.php`. Les dépendances, caches, fichiers SQLite actifs et coffres privés sont exclus du périmètre du formateur.

Le lint Twig contrôle la syntaxe ; il ne remplace pas la lecture des conditions et de leurs noms. Lors d’un renommage, vérifier notamment les propriétés du modèle (par exemple `comparison.a`) et éviter qu’une variable de boucle masque un filtre de page.

## Services ajoutés lors des corrections

- `CharterRenderer` produit un HTML Markdown sûr et structuré ; Twig ne découpe plus le document par ligne.
- `CausalGraph` normalise et valide les catégories supplémentaires, les relations et les cycles du sous-graphe causal.
- `AuditPrivacy` sépare les noms du journal et atteste l’expurgation des charges anciennes. Le moteur vérifie ces attestations avec les signatures originales.
- `ProposalSourceType` définit les champs d’une source dans la révision native Symfony.
- `tests/corrections.php` couvre les migrations de preuve, la charte et les parcours HTTP corrigés. Les tests de falsification sont distincts des seuls contrôles de syntaxe.
