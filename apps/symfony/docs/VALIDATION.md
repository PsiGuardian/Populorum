# Vérifications exécutées — socle Symfony/SQLite

29 octobre 2026. Résultat : **99 contrôles réussis, 0 échec**.

`tests/integration.php` exécute le moteur PHP et le véritable Kernel Symfony 8.1, les pages Twig, les formulaires Symfony et CSRF, les services du bundle effectivement installé et une copie temporaire de la base SQLite via Doctrine. Le compte public est réellement authentifié par Guardian : sans cookie valide, profiler et toolbar sont refusés ; après connexion, un profil Symfony natif est collecté et consultable.

Sont notamment vérifiés : les trois villes, les codes INSEE des fixtures, l’admissibilité, le consentement et son retrait, les trois votes et leur révision, le gel, la clôture, quorum et égalité, l’absence de duplication, le versionnement, les observations sourcées et datées, la comptabilité, la gouvernance et les recours, les signatures et falsifications, le rejeu, la persistance SQLite, les erreurs CSRF, les écritures à révision périmée, la restriction du compte public, le refus de PHPInfo, et l’expurgation des secrets du collecteur.

Autres vérifications réussies : syntaxe de 22 fichiers PHP, lint de 16 templates Twig, lint YAML, concordance du schéma Doctrine et du SQLite, intégrité SQLite, `composer validate --strict`, résolution Composer sans avis de vulnérabilité connu retourné lors de cette exécution.

Le compte rendu détaillé est dans `test-results.txt`.

Ces résultats ne sont pas un audit exhaustif de sécurité, d’accessibilité ou de charge, ni une validation des 68 scénarios complets du cahier des charges. Aucun serveur public PHP n’a été déployé par cette étape. Les essais HTTP sont exécutés à travers HttpKernel, sans navigateur ni serveur externe. Les clés locales, le coffre et les simulations d’essai ne sont pas versionnés.


## Relecture et présentation du code

Le refactoring de lisibilité conserve les états métier : 24 actions ont été comparées à la version précédente, avec des états identiques après normalisation des identifiants et horodatages générés. Leurs signatures restent valides. La suite permanente compte désormais 99 contrôles, dont trois vérifications des fiches territoriales filtrées.

Les 23 fichiers PHP du périmètre de style respectent la configuration PHP CS Fixer ; `composer style:check` ne détecte aucun écart. Ce contrôle est ajouté à GitHub Actions. Les templates Twig et le CSS sont développés et indentés ; le catalogue des champs et les méthodes métier portent des noms explicites. Aucun format de base SQLite, export ou preuve cryptographique n’est migré par cette intervention.
