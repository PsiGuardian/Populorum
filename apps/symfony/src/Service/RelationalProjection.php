<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/** Projections reconstruites dans la même transaction que le snapshot/CAS : aucune seconde voix possible. */
final class RelationalProjection
{
    private const COLLECTIONS = [
        'citizens' => 'citizen_records',
        'proposals' => 'proposal_records',
        'consultations' => 'consultation_records',
        'votes' => 'vote_records',
        'experiments' => 'experiment_records',
        'events' => 'audit_event_records',
        'cases' => 'moderation_records',
        'appeals' => 'appeal_records',
        'finance' => 'finance_records',
        'ruleSets' => 'rule_set_records',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function write(string $scenarioId, array $state): void
    {
        foreach (
            array_merge(array_values(self::COLLECTIONS), [
                'proposal_revision_records',
                'causal_node_records',
                'causal_edge_records',
                'observation_records',
            ]) as $table
        ) {
            $this->connection->delete($table, ['scenario_id' => $scenarioId]);
        }
        foreach (self::COLLECTIONS as $collection => $table) {
            foreach ($state[$collection] ?? [] as $item) {
                $extra =
                    $collection === 'votes'
                        ? [
                            'citizen_id' => $item['citizenId'],
                            'consultation_id' => $item['consultationId'],
                        ]
                        : [];
                $this->insert(
                    $table,
                    $scenarioId,
                    $item['id'],
                    $item,
                    null,
                    $extra,
                );
            }
        }
        foreach ($state['proposals'] as $proposal) {
            foreach ($proposal['revisions'] as $revision) {
                $this->insert(
                    'proposal_revision_records',
                    $scenarioId,
                    $proposal['id'] . ':' . $revision['version'],
                    $revision,
                    $proposal['id'],
                );
            }
            foreach ($proposal['nodes'] as $node) {
                $this->insert(
                    'causal_node_records',
                    $scenarioId,
                    $proposal['id'] . ':' . $node['id'],
                    $node,
                    $proposal['id'],
                );
            }
            foreach ($proposal['edges'] as $index => $edge) {
                $this->insert(
                    'causal_edge_records',
                    $scenarioId,
                    $proposal['id'] . ':' . $index,
                    $edge,
                    $proposal['id'],
                );
            }
        }
        foreach ($state['experiments'] as $experiment) {
            foreach ($experiment['observations'] as $observation) {
                $this->insert(
                    'observation_records',
                    $scenarioId,
                    $experiment['id'] . ':' . $observation['id'],
                    $observation,
                    $experiment['id'],
                );
            }
        }
    }

    private function insert(
        string $table,
        string $scenarioId,
        string $id,
        array $data,
        ?string $parent = null,
        array $extra = [],
    ): void {
        $this->connection->insert(
            $table,
            [
                'scenario_id' => $scenarioId,
                'object_id' => $id,
                'territory_id' => $data['territoryId'] ?? null,
                'parent_id' => $parent,
                'version' => $data['version'] ?? 1,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
            ] + $extra,
        );
    }
}
