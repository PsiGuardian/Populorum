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
        private ?GeographyCatalog $geography = null,
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
        $pilots = json_decode(
            (string) file_get_contents($this->projectDir . '/data/cities.json'),
            true, 512, JSON_THROW_ON_ERROR,
        );
        $municipalities = $this->connection->fetchAllAssociative('SELECT * FROM municipalities');
        $byCode = array_column($municipalities, null, 'code');
        $ordered = [];
        foreach ($pilots as $pilot) {
            if (!isset($byCode[$pilot['code']])) {
                throw new \RuntimeException('Préparez les communes du scénario pédagogique.');
            }
            $ordered[] = $byCode[$pilot['code']];
        }
        // The sample's t1/t2/t3 identifiers are stable in data/cities.json, not a national catalog.
        $state['territories'] = array_map(
            static fn (array $pilot, array $municipality): array => [
                'id' => $pilot['id'],
                'name' => $municipality['name'],
                'code' => $municipality['code'],
                'region' => $municipality['region'],
                'department' => $municipality['department'],
                'vintage' => (int) $municipality['vintage'],
                'sourceUrl' => $municipality['source_url'],
                'fictional' => false,
                'history' => [],
            ], $pilots, $ordered,
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

    /** Espace fictif vide, isolé pour chaque commune INSEE 2026. */
    private function seedForCommune(string $code): array
    {
        $commune = $this->geography?->find($code);
        if ($commune === null) {
            throw new \DomainException('Commune INSEE indisponible : installez le référentiel géographique.', 404);
        }
        $state = json_decode((string) file_get_contents($this->projectDir . '/data/reference-scenario.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['citizens', 'proposals', 'consultations', 'votes', 'experiments', 'adaptations',
            'finance', 'governance', 'cases', 'appeals', 'alerts', 'resources', 'nodes',
            'events', 'operations'] as $collection) {
            $state[$collection] = [];
        }
        $territoryId = 'c' . $code;
        $state['territories'] = [[
            'id' => $territoryId, 'name' => $commune['name'], 'code' => $code,
            'region' => $commune['region'], 'department' => $commune['department'],
            'vintage' => 2026, 'sourceUrl' => $commune['source'],
            'fictional' => false, 'history' => [],
        ]];
        // Participant logiciel de démonstration ; pas un habitant réel de la commune.
        $state['citizens'] = [[
            'id' => 'u1', 'name' => 'Participant fictif', 'territoryId' => $territoryId,
            'familyId' => 'family-demo-' . $code, 'role' => 'citizen',
            'consent' => true, 'suspended' => false, 'fictional' => true, 'history' => [],
        ]];
        $state['preferences']['dataset'] = 'national-communes-2026-v1';
        $state['preferences']['votingUnit'] = 'family';
        $state['preferences']['notice'] = 'Commune réelle INSEE ; simulation fictive vide. Aucun habitant, vote, budget ou résultat réel inventé.';
        $state['keyPair'] = null;

        return $state;
    }

    /** @return array{revision: int, state: array} */
    public function workspace(string $owner): array
    {
        $query = 'SELECT revision, state FROM simulations WHERE id = ?';
        $row = PerformanceTrace::measure(
            'db_read',
            fn () => $this->connection->fetchAssociative($query, [$owner]),
        );

        if (!$row) {
            $local = preg_match('/:commune:((?:[0-9]{5}|2[AB][0-9]{3}))\\z/D', $owner, $match) === 1;
            $state = $local ? $this->seedForCommune($match[1]) : $this->seed();
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
                    'INSERT OR IGNORE INTO simulations (id, revision, state, page_state, overview_state, updated_at) VALUES (?, 0, ?, ?, ?, ?)',
                    [
                        $owner,
                        json_encode($state, JSON_THROW_ON_ERROR),
                        PageState::encode($state, 0),
                        PageState::encode($state, 0, false),
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
            'state' => PerformanceTrace::measure(
                'json_decode',
                fn () => json_decode(
                    $row['state'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ];
    }

    /** Same row/revision as the authoritative snapshot. Legacy projections are rebuilt lazily. */
    public function pageWorkspace(string $owner, bool $includeVotes = true): array
    {
        // Column names are code constants, never request input.
        $column = $includeVotes ? 'page_state' : 'overview_state';
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $row = PerformanceTrace::measure(
                'db_read',
                fn () => $this->connection->fetchAssociative(
                    'SELECT revision, ' . $column . ' AS page_state FROM simulations WHERE id = ?',
                    [$owner],
                ),
            );
            if ($row && $row['page_state'] !== null) {
                try {
                    $view = PerformanceTrace::measure(
                        'json_decode',
                        fn () => json_decode(
                            $row['page_state'],
                            true,
                            512,
                            JSON_THROW_ON_ERROR,
                        ),
                    );
                    if (
                        ($view['version'] ?? null) === PageState::VERSION
                        && ($view['revision'] ?? null) ===
                            (int) $row['revision']
                        && ($view['includesVotes'] ?? true) === $includeVotes
                        && is_array($view['state'] ?? null)
                    ) {
                        return [
                            'revision' => (int) $row['revision'],
                            'state' => $view['state'],
                        ];
                    }
                } catch (\JsonException) {
                    // A derived read model can always be reconstructed from the complete snapshot.
                }
            }
            $full = $this->workspace($owner);
            $encoded = PageState::encode($full['state'], $full['revision'], $includeVotes);
            $updated = $this->connection->executeStatement(
                'UPDATE simulations SET ' . $column . ' = ? WHERE id = ? AND revision = ?',
                [$encoded, $owner, $full['revision']],
            );
            if ($updated === 1) {
                return [
                    'revision' => $full['revision'],
                    'state' => json_decode(
                        $encoded,
                        true,
                        512,
                        JSON_THROW_ON_ERROR,
                    )['state'],
                ];
            }
        }
        throw new \DomainException('Le laboratoire évolue rapidement : rechargez la page.', 409);
    }

    public function save(string $owner, int $revision, array $state): void
    {
        // Read before the write transaction: the CAS below rejects any intervening revision.
        // This avoids upgrading a deferred SQLite read lock while retaining atomic writes.
        $snapshot = $this->connection->fetchOne('SELECT state FROM simulations WHERE id = ? AND revision = ?', [$owner, $revision]);
        if ($snapshot === false) {
            throw new \DomainException('Écriture concurrente détectée : rechargez avant de recommencer.', 409);
        }
        $previous = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        $this->connection->transactional(function () use (
            $owner,
            $revision,
            $state,
            $previous,
        ): void {
            $affectedRows = $this->connection->executeStatement(
                'UPDATE simulations SET state = ?, page_state = ?, overview_state = ?, revision = revision + 1, updated_at = ? WHERE id = ? AND revision = ?',
                [
                    json_encode($state, JSON_THROW_ON_ERROR),
                    PageState::encode($state, $revision + 1),
                    PageState::encode($state, $revision + 1, false),
                    gmdate('c'),
                    $owner,
                    $revision,
                ],
            );
            if ($affectedRows !== 1) {
                throw new \DomainException('Écriture concurrente détectée : rechargez avant de recommencer.', 409);
            }
            $this->projection->write($owner, $state, $previous);
            foreach ($state['events'] as $event) {
                $this->connection->executeStatement(
                    'INSERT OR IGNORE INTO audit_outbox (event_id, scenario_id, dispatched) VALUES (?, ?, 0)',
                    [$event['id'], $owner],
                );
            }
        });
    }

    /** Synchronise le référentiel national COG 2026 dans Doctrine, sans créer de citoyens ni votes. */
    public function importCities(): int
    {
        $file = $this->projectDir . '/data/geography/communes-2026.sqlite';
        if (!is_file($file)) {
            throw new \RuntimeException('Référentiel INSEE absent : exécutez python3 tools/install_reference_indexes.py avant app:demo:prepare.');
        }
        $source = new \PDO('sqlite:file:' . $file . '?mode=ro', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $source->exec('PRAGMA query_only=ON');
        $year = $source->query("SELECT value FROM metadata WHERE key='vintage'")->fetchColumn();
        if ($year !== '2026') {
            throw new \RuntimeException('Référentiel INSEE incompatible : millésime 2026 exigé.');
        }
        $count = (int) $source->query('SELECT COUNT(*) FROM communes')->fetchColumn();
        if ($count < 30000) {
            throw new \RuntimeException('Référentiel INSEE incomplet.');
        }

        $select = $source->query('SELECT code, name, region_name, department_name FROM communes ORDER BY code');
        $this->connection->transactional(function () use ($select): void {
            $insert = $this->connection->prepare(<<<'SQL'
                INSERT INTO municipalities (code, name, region, department, vintage, source_url)
                VALUES (?, ?, ?, ?, 2026, ?)
                ON CONFLICT(code) DO UPDATE SET
                    name = excluded.name, region = excluded.region,
                    department = excluded.department, vintage = excluded.vintage,
                    source_url = excluded.source_url
                SQL);
            while (($city = $select->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $insert->bindValue(1, $city['code']);
                $insert->bindValue(2, $city['name']);
                $insert->bindValue(3, $city['region_name']);
                $insert->bindValue(4, $city['department_name']);
                $insert->bindValue(5, 'https://www.insee.fr/fr/information/8740222');
                $insert->executeStatement();
            }
        });

        return $count;
    }
}
