# Baseline Symfony/SQLite — 0.3.0

29 octobre 2026. Succède à [0.2.0](BASELINE-0.2.md). Le projet reste Symfony natif, PHP 8.4+, Doctrine, Twig, SQLite, trois communes. Profiler Shield reste figé à `3595f1e483e4ea1ee2dcbd6ddbabe7e2976e1783` sur `dev-1.0.1` ; son dépôt n’a pas été modifié.

## Organisation

- `SimulationEngine` : invariants, opérations et signatures ; `CivicWorkflow` : rôles, décisions et cycles ; `CausalGraph` : validation des liens ; `ConvergenceAnalyzer` : rapprochement descriptif.
- `DemoDatabase` : snapshot signé/CAS ; `RelationalProjection` : tables métier dans la même transaction ; migrations : évolution du schéma sans écrasement des espaces.
- `DemoController`, formulaires Symfony et Twig : parcours serveur utilisables sans JavaScript ; SSE est un enrichissement facultatif.
- `CivicAccount` et sécurité Symfony : accès fictifs liés à un seul personnage. Guardian reste indépendant et public en lecture seule.
- `BackupService`, `ReplicaProtocol`, `VoluntaryAgent` : restauration authentifiée, copies publiques et consentement local révocable.
- `MunicipalFixtureBuilder` : génération déterministe de 300 personnages et 27 projets documentés, sans résultat réel allégué.

## Contrats

Une voix active par personnage admissible et consultation, règles et texte figés, consentement fictif, autonomie locale, aucun score humain ni sanction automatique. Clés, sessions, comptes privés, mots de passe et CSV restent hors de Git et des exports publics. Les sauvegardes ne sont pas des répliques publiques. Le schéma SQL projeté ne prétend pas normaliser toutes les entités conceptuelles du cahier des charges.

## Vérification et limites

Voir [la revue courante](COMPLETION_REVIEW.md), [l’exploitation](OPERATIONS.md) et [les sources des fixtures](MUNICIPAL_FIXTURES.md). Les matrices initiales sont historiques : leurs constats « absent » ne décrivent plus nécessairement ce lot. La revue courante conserve les écarts qui demandent une recette plus étendue ou une décision de déploiement. Aucun audit externe, validation juridique, identité civile, consensus distribué ou engagement de disponibilité n’est acquis par les tests locaux.
