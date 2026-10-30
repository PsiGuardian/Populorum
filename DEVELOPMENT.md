# Développement de Populorum

La version applicative de référence de cette branche se trouve dans **[apps/symfony](apps/symfony/README.md)** : PHP 8.4, Symfony 8.1, Doctrine, Twig et SQLite.

- [Baseline Symfony/SQLite et périmètre de cette étape](apps/symfony/docs/BASELINE.md).
- [Base SQLite initiale](apps/symfony/data/populorum-demo.sqlite), limitée à Paris, Marseille et Le Havre.
- [Export SQL lisible](apps/symfony/data/populorum-demo.sql).
- [Identifiants publics du profiler de démonstration](profiler-shield.md).
- [Contrôles exécutés](apps/symfony/docs/test-results.txt).

La première tentative React/Workers/D1 ne correspondait pas à la pile imposée par le cahier des charges. Elle est conservée dans `apps/sites` sur cette branche historique pour référence ; la version Symfony à tester se trouve dans `apps/symfony`. Les documents fondateurs et les 28 langues du README existant restent inchangés.

Le [guide de lecture et de style](apps/symfony/docs/CODE_STYLE.md) présente les responsabilités des fichiers, le parcours des mutations et les commandes de contrôle du code.

## Version à valider avant intégration dans main

Cette branche contient désormais la version complète « Montée en charge ×5 » de l’archive remise le 30 octobre 2026 (SHA-256 : `cf40fa48cc1b1c7839cde89ae9a858c1d718c7fb08b1426b2925bcf4292813c3`). Les changements d’intégration se limitent à la mise en forme PHP, au `.gitignore` racine et à cette clarification du répertoire de référence. Les dépendances se réinstallent avec le `composer.lock` livré ; les fichiers privés et les données d’exécution ne sont pas versionnés.

- [Installation et migrations nécessaires](LIRE_AVANT_INSTALLATION.md).
- [Rapport de performances et mesures à ×5](apps/symfony/docs/PERFORMANCE_SCALE_REPORT.md).
- [Besoins, devis et participation habitante](apps/symfony/docs/PUBLIC_PROCUREMENT.md).

Le passage sur `main` attend les tests et la validation du mainteneur. Cette mise à jour de branche ne déploie aucun site.
