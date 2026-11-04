# Continuité des services essentiels — module Eau

Le référentiel existant `EssentialServices::CATALOG` couvre 28 fonctions. Les fiches publiques de préparation sont définies dans `ServiceContinuityPlans`. Aucun service réel n'est piloté par l'application.

## Interface

Ouvrir `/services?plan=water` puis sélectionner un scénario dans le panneau « Graphe cause → effet ». Les six identifiants stables sont :

- `contamination` : suspicion de contamination, y compris substances volatiles ;
- `intrusion` : défaut d'intégrité ou retour de fluides ;
- `leak` : rupture ou fuite ;
- `obstruction` : perte de débit ;
- `outage` : interruption énergétique prolongée ;
- `conflict` : perturbations multiples en crise civile ou conflit armé.

L'affichage provient de `WaterContinuityGraph`. Les flèches indiquent des dépendances possibles, pas des prédictions. Les nœuds affectés relèvent d'un scénario pédagogique fixe. Un approvisionnement de substitution n'est jamais automatiquement considéré comme sûr : sa validation sanitaire reste nécessaire.

## Contrat de sécurité

Le catalogue est public et fictif. Ne pas y ajouter d'implantations réelles, de schémas d'installations, de points d'accès, d'inventaires stratégiques, de paramètres de supervision ni de procédures permettant de contourner des protections. Les diagnostics, restrictions d'usage et remises en service réelles relèvent des exploitants et autorités habilités.

## Validation

Depuis `apps/symfony` après `composer install` :

```bash
php tests/water_continuity.php
composer test
php bin/console lint:twig templates/pages/services.html.twig templates/partials/water-causal-graph.html.twig templates/partials/service-continuity-plan.html.twig
php bin/console debug:router site_services
```

Le test contrôle le maintien des 28 services, l'intégrité de leurs dépendances, les six scénarios et les extrémités des liens du graphe. Il ne constitue pas une validation sanitaire ni un essai de résilience réel.

## Évolutions prévues

Conserver les conséquences comme hypothèses explicites, ajouter un modèle temporel des rétablissements, gérer les ressources fictives et l'autonomie par commune, permettre l'évaluation comparative des réponses et conserver les retours d'expérience intergénérationnels. Ne pas inférer un risque réel à partir du seul graphe.
