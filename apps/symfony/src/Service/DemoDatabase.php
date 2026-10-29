<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/** Persistance des scénarios isolés, avec verrouillage optimiste dans SQLite. */
final class DemoDatabase
{
    public function __construct(
        private Connection $connection,
        private string $projectDir,
        private RelationalProjection $projection,
    ) {
    }

    public function seed(): array
    {
        $reference = ($_SERVER['POPULORUM_SCENARIO'] ?? '') === 'reference';
        $contents = file_get_contents(
            $this->projectDir .
                '/data/' .
                ($reference ? 'reference-scenario.json' : 'scenario.json.gz'),
        );
        $state = json_decode(
            $reference ? $contents : gzdecode($contents),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $municipalities = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT *
            FROM municipalities
            ORDER BY CASE code
                WHEN '75056' THEN 1
                WHEN '13055' THEN 2
                ELSE 3
            END
            SQL,
        );

        if (count($municipalities) !== 3) {
            throw new \RuntimeException('Exécutez la préparation de la base de démonstration.');
        }

        // L’ordre fixe conserve les identifiants t1/t2/t3 utilisés par le scénario pédagogique.
        $state['territories'] = array_map(
            fn (array $municipality, int $index): array => [
                'id' => 't' . ($index + 1),
                'name' => $municipality['name'],
                'code' => $municipality['code'],
                'region' => $municipality['region'],
                'department' => $municipality['department'],
                'vintage' => (int) $municipality['vintage'],
                'sourceUrl' => $municipality['source_url'],
                'fictional' => false,
                'history' => [],
            ],
            $municipalities,
            array_keys($municipalities),
        );
        $statistics = json_decode(
            file_get_contents(
                $this->projectDir .
                    '/data/research/ssmsi-2024-2025-three-cities.json',
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        foreach ($state['territories'] as &$territory) {
            foreach ($statistics['rows'] as $row) {
                if ($row['CODGEO_2026'] === $territory['code']) {
                    $territory['population'] = (int) $row['insee_pop'];
                    $territory['populationYear'] =
                        (int) $row['insee_pop_millesime'];
                    $territory['populationSource'] = $statistics['source'];
                    break;
                }
            }
        }
        unset($territory);
        $state['keyPair'] = null;

        return $state;
    }

    /** @return array{revision: int, state: array} */
    public function workspace(string $owner): array
    {
        $query = 'SELECT revision, state FROM simulations WHERE id = ?';
        $row = $this->connection->fetchAssociative($query, [$owner]);

        if (!$row) {
            $state = $this->seed();
            if (
                str_starts_with(
                    $state['preferences']['dataset'] ?? '',
                    'municipal-2026-v',
                )
            ) {
                $state = new SimulationEngine()->initializeFixture($state);
            }

            // Une seconde requête concurrente peut avoir créé le scénario entre-temps.
            $this->connection->transactional(function () use (
                $owner,
                $state,
            ): void {
                $created = $this->connection->executeStatement(
                    'INSERT OR IGNORE INTO simulations (id, revision, state, updated_at) VALUES (?, 0, ?, ?)',
                    [
                        $owner,
                        json_encode($state, JSON_THROW_ON_ERROR),
                        gmdate('c'),
                    ],
                );
                if ($created === 1) {
                    $this->projection->write($owner, $state);
                }
            });
            $row = $this->connection->fetchAssociative($query, [$owner]);
        }

        return [
            'revision' => (int) $row['revision'],
            'state' => json_decode(
                $row['state'],
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
        ];
    }

    public function save(string $owner, int $revision, array $state): void
    {
        $this->connection->transactional(function () use (
            $owner,
            $revision,
            $state,
        ): void {
            $affectedRows = $this->connection->executeStatement(
                'UPDATE simulations SET state = ?, revision = revision + 1, updated_at = ? WHERE id = ? AND revision = ?',
                [
                    json_encode($state, JSON_THROW_ON_ERROR),
                    gmdate('c'),
                    $owner,
                    $revision,
                ],
            );
            if ($affectedRows !== 1) {
                throw new \DomainException('Écriture concurrente détectée : rechargez avant de recommencer.', 409);
            }
            $this->projection->write($owner, $state);
            foreach ($state['events'] as $event) {
                $this->connection->executeStatement(
                    'INSERT OR IGNORE INTO audit_outbox (event_id, scenario_id, dispatched) VALUES (?, ?, 0)',
                    [$event['id'], $owner],
                );
            }
        });
    }

    public function importCities(): int
    {
        $cities = json_decode(
            file_get_contents($this->projectDir . '/data/cities.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $insertedCities = 0;

        foreach ($cities as $city) {
            $insertedCities += $this->connection->executeStatement(
                <<<'SQL'
                INSERT OR IGNORE INTO municipalities (code, name, region, department, vintage, source_url)
                VALUES (?, ?, ?, ?, ?, ?)
                SQL,
                [
                    $city['code'],
                    $city['name'],
                    $city['region'],
                    $city['department'],
                    $city['vintage'],
                    $city['sourceUrl'],
                ],
            );
        }

        return $insertedCities;
    }
}
