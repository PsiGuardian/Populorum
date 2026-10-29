# Baseline de développement — 0.1.0

Date : 29 octobre 2026. Source documentaire : branche main de PsiGuardian/Populorum, état récupéré au début de cette réalisation ; charte 1.0 et cahier des charges 1.0. La copie de lecture `REFERENCE.fr.md` n’amende pas le document d’origine.

## Invariants issus de la charte

1. Simulation visible partout ; aucun effet juridique et aucune identité réelle saisie.
2. Pas de score moral, classement des citoyens, réputation politique ni argument d’autorité.
3. Raisonnements autonomes ; causes, prévisions, observations et sources distinctes.
4. Une voix active par identité fictive admissible, sans privilège technique.
5. Les règles, personnes admissibles et version soumise sont figées à l’ouverture.
6. Pas de transfert de votes entre territoires ; reprise volontaire par un brouillon local.
7. Révisions motivées et conséquences imprévues conservées.
8. Toute mutation est validée côté serveur, enregistrée atomiquement et auditée.
9. Une alerte ouvre une vérification ; elle ne sanctionne personne.
10. Les relais restent virtuels et révocables ; aucune ressource d’un appareil n’est partagée.

## Décisions d’architecture

**ADR-001 — Adaptateur Sites distinct de la cible Symfony.** La plateforme Sites exécute des Workers JavaScript et provisionne D1. Livrer une application réellement hébergeable ici exige cette adaptation. La cible PHP 8.4 / Symfony 8.1 / Doctrine / PostgreSQL n’est pas remplacée dans les exigences. Cette baseline est une branche de démonstration, pas la validation de la phase 1 Symfony.

**ADR-002 — Espaces de simulation isolés.** Le compte ChatGPT donne accès à son propre espace de personnages fictifs. L’utilisateur pilote plusieurs personnages pédagogiques : cela ne prétend pas empêcher les comptes civiques multiples en situation réelle. Aucun e-mail ni nom ChatGPT dans les exports. Les mutations anonymes sont refusées. Le Site reste privé à son propriétaire à la livraison.

**ADR-003 — Snapshot transactionnel.** D1 conserve une ligne JSON versionnée par propriétaire, pour permettre un CAS atomique : `UPDATE ... WHERE owner = ? AND revision = ?`. Une écriture concurrente reçoit 409. Ce choix assure l’atomicité du pilote, mais n’est pas la modélisation relationnelle PostgreSQL de la cible. Limite pédagogique : 10 000 opérations. Pas de revendication de montée en charge nationale.

**ADR-004 — Intégrité bornée.** Chaînage SHA-256 du JSON canonique et signatures ECDSA P-256 ; chaque événement comprend le changement demandé et l’empreinte de l’état. Contrôle avant toute mutation. La clé privée reste dans la base, hors API publique, exports et Git. L’opérateur reste dans le modèle de confiance ; il pourrait réécrire une chaîne avec une clé compromise. Pas de journal externe indépendant ni consensus distribué revendiqué.

**ADR-005 — Règles de simulation publiques.** Quorum paramétrable 0–100 %, majorité stricte des exprimés, égalité non adoptée, abstentions dans la participation, changements permis avant clôture, éligibilité locale figée à l’ouverture. Clôture pédagogique anticipée possible par l’auteur fictif avec motif. Ces valeurs ne valent pas règles électorales réelles.

**ADR-006 — Convergence explicable.** Intersection des thèmes et égalité textuelle des indicateurs ; ≥2 thèmes communs = proximité partielle, 1 = thématique, 0 = chemins distincts. Aucune inférence scientifique ni évaluation d’auteur. Les quatre scénarios visuels sont des modèles pédagogiques indépendants.

**ADR-007 — Localisation.** Interface en français, lecture de la charte dans les 28 locales existantes, langue/RTL du texte signalés, français de référence préservé dans la consultation. Traduction complète de l’interface et contrôles éditoriaux à poursuivre.

**ADR-008 — Territoires.** Communes et démographie entièrement fictives avec codes `SIM-*`, jamais présentés comme données INSEE. Import du COG 2026, historiques et arrondissements à réaliser dans le socle de référence.

**ADR-009 — Licence du nouveau code.** MIT pour le nouvel adaptateur Sites. Aucun changement automatique de licence des documents existants. Le choix d’une licence de service de type AGPL pour la future cible reste une décision de gouvernance séparée.

## Prochaines phases nécessaires à la conformité intégrale

- Socle Symfony/PostgreSQL et migrations Doctrine, authentification prévue, architecture métier modulaire, installation Arch vérifiée.
- Import COG officiel, historique des millésimes, rattachements et statuts particuliers.
- Schéma causal normalisé, éditeur de liens typés complet, preuves par observation, reformulations versionnées et comparaison détaillée.
- Workflow complet avec états juridiques/territoriaux, échéancier de consultation, réclamations et gouvernance collégiale exécutoire sous contrôle humain.
- Réseau de miroirs réellement indépendants, protocole signé, agent volontaire, limites réseau réelles, pannes et restauration.
- Sauvegardes chiffrées, restauration testée, observabilité d’exploitation et métriques de performance mesurées.
- Recette complète des 68 cas, tests de charge, audit d’accessibilité WCAG/RGAA et audit de sécurité indépendants.
- Avant tout usage réel : responsables, bases légales, analyse d’impact, vérification d’identité, règles d’éligibilité et protection des données sensibles.

Aucune de ces exigences restantes n’est marquée « réussie » par simple existence d’une page.
