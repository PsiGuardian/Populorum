<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Fictional public cause-effect model. No installation layout, control parameters,
 * access points, live assets or operational instructions are exposed.
 */
final class WaterContinuityGraph
{
    public const NODES = [
        'source' => ['label' => 'Ressource et captage', 'kind' => 'water'],
        'treatment' => ['label' => 'Traitement et contrôles', 'kind' => 'water'],
        'storage' => ['label' => 'Stockage sanitaire', 'kind' => 'water'],
        'distribution' => ['label' => 'Distribution', 'kind' => 'water'],
        'households' => ['label' => 'Accès des habitants', 'kind' => 'population'],
        'energy' => ['label' => 'Énergie', 'kind' => 'dependency'],
        'transport' => ['label' => 'Acheminement', 'kind' => 'dependency'],
        'communications' => ['label' => 'Communications', 'kind' => 'dependency'],
        'health' => ['label' => 'Santé', 'kind' => 'dependency'],
        'alternative' => ['label' => 'Approvisionnement de substitution contrôlé', 'kind' => 'response'],
    ];

    /** Direction: upstream failure may affect downstream function, not certainty. */
    public const EDGES = [
        ['from' => 'source', 'to' => 'treatment', 'effect' => 'Qualité ou volume de ressource incertains'],
        ['from' => 'treatment', 'to' => 'storage', 'effect' => 'Disponibilité d’eau validée incertaine'],
        ['from' => 'storage', 'to' => 'distribution', 'effect' => 'Capacité de desserte réduite'],
        ['from' => 'distribution', 'to' => 'households', 'effect' => 'Accès à l’eau perturbé'],
        ['from' => 'energy', 'to' => 'treatment', 'effect' => 'Traitement potentiellement indisponible'],
        ['from' => 'energy', 'to' => 'distribution', 'effect' => 'Distribution potentiellement dégradée'],
        ['from' => 'communications', 'to' => 'distribution', 'effect' => 'Coordination des interventions perturbée'],
        ['from' => 'transport', 'to' => 'alternative', 'effect' => 'Approvisionnement de secours ralenti'],
        ['from' => 'distribution', 'to' => 'health', 'effect' => 'Soins et hygiène affectés'],
        ['from' => 'alternative', 'to' => 'households', 'effect' => 'Accès partiellement rétabli après validation sanitaire'],
    ];

    public const SCENARIOS = [
        'contamination' => ['source', 'treatment', 'storage', 'distribution', 'households', 'health'],
        'intrusion' => ['distribution', 'households', 'health'],
        'leak' => ['distribution', 'households', 'health'],
        'obstruction' => ['distribution', 'households', 'health'],
        'outage' => ['energy', 'treatment', 'distribution', 'households', 'health'],
        'conflict' => ['energy', 'transport', 'communications', 'treatment', 'alternative', 'distribution', 'households', 'health'],
    ];

    public static function forScenario(string $id): array
    {
        if (!isset(self::SCENARIOS[$id])) {
            throw new \InvalidArgumentException('Scénario Eau inconnu.');
        }

        $affected = array_fill_keys(self::SCENARIOS[$id], true);
        $nodes = [];
        foreach (self::NODES as $key => $node) {
            $nodes[] = ['id' => $key, 'affected' => isset($affected[$key])] + $node;
        }

        return [
            'scenario' => $id,
            'nodes' => $nodes,
            'edges' => self::EDGES,
            'notice' => 'Les liens représentent des dépendances possibles, pas une prévision certaine ni un plan opérationnel.',
        ];
    }
}
