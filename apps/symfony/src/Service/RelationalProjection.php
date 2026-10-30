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

    public function write(string $scenarioId, array $state, ?array $previous = null): void
    {
        if ($previous !== null) {
            foreach (array_keys(self::COLLECTIONS) as $collection) {
                $ids = array_column($state[$collection] ?? [], 'id');
                if (count($ids) !== count(array_unique($ids))) {
                    // Preserve the database constraint diagnostics and transaction rollback.
                    $this->write($scenarioId, $state);

                    return;
                }
            }
            $this->writeChanges($scenarioId, $state, $previous);

            return;
        }
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
        $this->writeProposalDetails($scenarioId, $state['proposals']);
        $this->writeExperimentDetails($scenarioId, $state['experiments']);
    }

    private function writeChanges(string $scenarioId, array $state, array $previous): void
    {
        $changed = [];
        foreach (self::COLLECTIONS as $collection => $table) {
            $old = array_column($previous[$collection] ?? [], null, 'id');
            $current = array_column($state[$collection] ?? [], null, 'id');
            foreach ($old as $id => $item) {
                if (!isset($current[$id])) {
                    $this->connection->delete($table, ['scenario_id' => $scenarioId, 'object_id' => $id]);
                    $changed[$collection][$id] = true;
                }
            }
            $pending = [];
            foreach ($current as $id => $item) {
                if (isset($old[$id]) && $old[$id] === $item) {
                    continue;
                }
                $this->connection->delete($table, ['scenario_id' => $scenarioId, 'object_id' => $id]);
                $pending[$id] = $item;
            }
            foreach ($pending as $id => $item) {
                $extra = $collection === 'votes' ? ['citizen_id' => $item['citizenId'], 'consultation_id' => $item['consultationId']] : [];
                $this->insert($table, $scenarioId, $item['id'], $item, null, $extra);
                $changed[$collection][$id] = true;
            }
        }
        foreach (['proposals' => ['proposal_revision_records', 'causal_node_records', 'causal_edge_records'], 'experiments' => ['observation_records']] as $collection => $tables) {
            foreach (array_keys($changed[$collection] ?? []) as $id) {
                foreach ($tables as $table) {
                    $this->connection->delete($table, ['scenario_id' => $scenarioId, 'parent_id' => $id]);
                }
            }
        }
        $this->writeProposalDetails($scenarioId, array_values(array_filter($state['proposals'], static fn (array $item): bool => isset($changed['proposals'][$item['id']]))));
        $this->writeExperimentDetails($scenarioId, array_values(array_filter($state['experiments'], static fn (array $item): bool => isset($changed['experiments'][$item['id']]))));
    }

    private function writeProposalDetails(string $scenarioId, array $proposals): void
    {
        foreach ($proposals as $proposal) {
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
    }

    private function writeExperimentDetails(string $scenarioId, array $experiments): void
    {
        foreach ($experiments as $experiment) {
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
