# Baseline Symfony/SQLite — 0.2.0

29 octobre 2026. Cette branche corrige l’écart de la première tentative Sites. Elle part de la branche documentaire `main` et ne contient pas l’application React/Workers/D1.

## Pile effectivement installée

- PHP 8.4 minimum ; essais exécutés sous PHP 8.4.5 dans l’environnement de réalisation.
- Symfony 8.1, Doctrine ORM/DBAL/Migrations, Twig, Form, Validator et CSRF.
- SQLite : un fichier local, limité pour l’instant aux communes de Paris, Marseille et Le Havre.
- Profiler Shield : branche `dev-1.0.1`, commit `3595f1e483e4ea1ee2dcbd6ddbabe7e2976e1783`, sans modification du dépôt du bundle.
- Interface rendue en Twig et moteur métier PHP ; aucun moteur JavaScript côté serveur.

## Invariants

Simulation visible, une voix active par personnage admissible, consentement fictif, version et règles figées, autonomie locale, révision tracée, sources et imprévus, aucun classement des citoyens, aucune sanction automatique sur signalement, clés exclues des exports et de Git.

Le compte public Guardian est `developer`, protégé par une restriction applicative en lecture seule. Ses identifiants sont intentionnellement publics. Aucune connexion HTTP de l’opérateur n’est autorisée. Les clés d’instance restent locales, sans rapport avec les identifiants publics. La collecte native du profiler n’est activée qu’après l’authentification Guardian et l’autorisation du bundle.

## Écarts restant à traiter par rapport au projet complet

Cette étape ne prétend pas achever le cahier des charges entier. La restriction à trois communes et le remplacement initial de PostgreSQL par SQLite sont demandés par l’utilisateur. La normalisation de l’ensemble des entités métier, l’historique territorial national, l’éditeur graphique avancé, les liens causaux typés complets, SSE/Mercure, l’agent volontaire et la fédération réelle, les sauvegardes chiffrées avec restauration testée et les audits WCAG/RGAA, de sécurité et de charge restent à réaliser.

Les villes sont réelles ; les personnes et données d’expérience sont fictives. Aucun accès de démonstration n’est utilisable pour une instance contenant des données réelles sensibles.
