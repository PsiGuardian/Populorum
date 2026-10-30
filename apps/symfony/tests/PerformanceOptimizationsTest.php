<?php

declare(strict_types=1);

use App\Service\ConvergenceAnalyzer;
use App\Service\DemoDatabase;
use App\Service\PublicBudget;
use App\Service\RelationalProjection;
use App\Service\SimulationEngine;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;

final class PerformanceOptimizationsTest extends TestCase
{
    private string $path;
    private Doctrine\DBAL\Connection $connection;
    private DemoDatabase $database;
    private mixed $oldScenario;

    protected function setUp(): void
    {
        $this->path =
            dirname(__DIR__) .
            '/var/performance-test-' .
            bin2hex(random_bytes(8)) .
            '.sqlite';
        copy(dirname(__DIR__) . '/data/populorum-demo.sqlite', $this->path);
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $this->path,
        ]);
        $this->database = new DemoDatabase(
            $this->connection,
            dirname(__DIR__),
            new RelationalProjection($this->connection),
        );
        $this->oldScenario = $_SERVER['POPULORUM_SCENARIO'] ?? null;
        $_SERVER['POPULORUM_SCENARIO'] = '';
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        foreach (
            [$this->path, $this->path . '-wal', $this->path . '-shm'] as $path
        ) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if ($this->oldScenario === null) {
            unset($_SERVER['POPULORUM_SCENARIO']);
        } else {
            $_SERVER['POPULORUM_SCENARIO'] = $this->oldScenario;
        }
    }

    public function testDisplayProjectionIsSmallerAndKeepsAuthoritativeHistoryAndSignatureIntact(): void
    {
        $full = $this->database->workspace('alpha');
        $view = $this->database->pageWorkspace('alpha');
        self::assertSame($full['revision'], $view['revision']);
        self::assertArrayNotHasKey('keyPair', $view['state']);
        self::assertArrayNotHasKey('operations', $view['state']);
        self::assertArrayHasKey('mindHistory', $full['state']['citizens'][0]);
        self::assertArrayNotHasKey(
            'mindHistory',
            $view['state']['citizens'][0],
        );
        self::assertArrayHasKey('deliberation', $full['state']['votes'][0]);
        self::assertArrayNotHasKey('deliberation', $view['state']['votes'][0]);
        self::assertLessThan(
            strlen(json_encode($full['state'])) * 0.6,
            strlen(json_encode($view['state'])),
        );
        self::assertSame($full, $this->database->workspace('alpha'));
        self::assertTrue(
            new SimulationEngine()->verify($full['state'])['valid'],
        );
        $engine = new SimulationEngine();
        self::assertSame(
            array_column(
                $engine->publicState($full['state'])['consultations'],
                'tally',
            ),
            array_column(
                $engine->publicState($view['state'])['consultations'],
                'tally',
            ),
        );
    }

    public function testDerivedReadModelIsUpdatedAtomicallyAndDoesNotCrossWorkspaces(): void
    {
        $alpha = $this->database->workspace('alpha');
        $this->database->workspace('beta');
        $next = new SimulationEngine()->apply(
            $alpha['state'],
            'worker.availability',
            ['citizenId' => 'u1', 'available' => false, 'optIn' => false],
            bin2hex(random_bytes(16)),
        );
        $this->database->save('alpha', $alpha['revision'], $next);
        $view = $this->database->pageWorkspace('alpha');
        self::assertSame(1, $view['revision']);
        self::assertFalse(
            array_column($view['state']['workers'], null, 'id')['u1'][
                'available'
            ],
        );
        self::assertTrue(
            array_column(
                $this->database->pageWorkspace('beta')['state']['workers'],
                null,
                'id',
            )['u1']['available'],
        );
        try {
            $this->database->save('alpha', 0, $alpha['state']);
            self::fail('Stale write accepted');
        } catch (DomainException $exception) {
            self::assertSame(409, $exception->getCode());
        }
        self::assertSame($view, $this->database->pageWorkspace('alpha'));
    }

    public function testLegacyStaleAndMalformedReadModelsAreRebuiltWithoutNewAuditEvents(): void
    {
        $original = $this->database->workspace('alpha');
        foreach (
            [
                null,
                '{broken',
                '{"version":999,"revision":0,"state":{}}',
                '{"version":1,"revision":-1,"state":{}}',
            ] as $invalid
        ) {
            $this->connection->executeStatement(
                'UPDATE simulations SET page_state = ? WHERE id = ?',
                [$invalid, 'alpha'],
            );
            $view = $this->database->pageWorkspace('alpha');
            self::assertSame($original['revision'], $view['revision']);
            self::assertCount(300, $view['state']['citizens']);
            self::assertSame($original, $this->database->workspace('alpha'));
        }
    }

    public function testExternalSnapshotUpdateInvalidatesTheDisplayProjection(): void
    {
        $full = $this->database->workspace('alpha');
        $next = new SimulationEngine()->apply(
            $full['state'],
            'worker.availability',
            ['citizenId' => 'u1', 'available' => false, 'optIn' => false],
            bin2hex(random_bytes(16)),
        );
        $this->connection->executeStatement(
            'UPDATE simulations SET state = ? WHERE id = ?',
            [json_encode($next, JSON_THROW_ON_ERROR), 'alpha'],
        );
        self::assertNull(
            $this->connection->fetchOne(
                'SELECT page_state FROM simulations WHERE id = ?',
                ['alpha'],
            ),
        );
        self::assertFalse(
            array_column(
                $this->database->pageWorkspace('alpha')['state']['workers'],
                null,
                'id',
            )['u1']['available'],
        );
    }

    public function testFailedProjectionTransactionRollsBackBothSnapshots(): void
    {
        $full = $this->database->workspace('alpha');
        $view = $this->database->pageWorkspace('alpha');
        $invalid = $full['state'];
        $invalid['votes'][] = $invalid['votes'][0];
        try {
            $this->database->save('alpha', 0, $invalid);
            self::fail('Duplicate projection should fail');
        } catch (UniqueConstraintViolationException) {
            self::assertSame($full, $this->database->workspace('alpha'));
            self::assertSame($view, $this->database->pageWorkspace('alpha'));
        }
    }

    public function testBatchTalliesExactlyMatchIndependentPerConsultationCalculations(): void
    {
        $state = $this->database->workspace('alpha')['state'];
        $engine = new SimulationEngine();
        $public = $engine->publicState($state);
        foreach ($state['consultations'] as $i => $consultation) {
            self::assertSame(
                $engine->tally($consultation, $state['votes']),
                $public['consultations'][$i]['tally'],
            );
        }
        self::assertTrue($engine->verify($public)['valid']);
        self::assertArrayNotHasKey(
            'tally',
            $engine->publicState($state, false)['consultations'][0],
        );
        $public['consultations'][0]['status'] = 'open';
        $public['consultations'][0]['closesAt'] = '2000-01-01T00:00:00Z';
        self::assertSame(
            'expired',
            $engine->publicState($public)['consultations'][0]['tally']['phase'],
        );
    }

    public function testVisibleConvergenceMatchesTheFullAnalysisForEveryFilter(): void
    {
        $state = $this->database->workspace('alpha')['state'];
        $analyzer = new ConvergenceAnalyzer();
        $full = $analyzer->analyze($state);
        $proposals = array_column($state['proposals'], null, 'id');
        foreach (['', 'p1', 'unknown'] as $selected) {
            foreach (['all', 't1', 't2', 't3'] as $territory) {
                $filtered = array_values(
                    array_filter(
                        $full,
                        static fn (array $pair): bool => ($selected === ''
                            || in_array(
                                $selected,
                                [$pair['a'], $pair['b']],
                                true,
                            ))
                            && ($territory === 'all'
                                || in_array(
                                    $territory,
                                    [
                                        $proposals[$pair['a']]['territoryId'],
                                        $proposals[$pair['b']]['territoryId'],
                                    ],
                                    true,
                                )),
                    ),
                );
                foreach ([1, 2, 10000] as $page) {
                    $result = $analyzer->page(
                        $state,
                        $selected,
                        $territory,
                        $page,
                    );
                    self::assertSame(count($filtered), $result['total']);
                    self::assertSame(
                        array_slice($filtered, ($page - 1) * 12, 12),
                        $result['items'],
                    );
                }
            }
        }
    }

    public function testOverviewIsAtomicRebuildableAndNeverReplacesFullHistory(): void
    {
        $source = $this->database->workspace('alpha');
        $overview = $this->database->pageWorkspace('alpha', false);
        self::assertArrayNotHasKey('votes', $overview['state']);
        self::assertSame($source['state']['proposals'], $overview['state']['proposals']);
        self::assertCount(2700, $this->database->pageWorkspace('alpha')['state']['votes']);
        self::assertTrue(new SimulationEngine()->verify($this->database->workspace('alpha')['state'])['valid']);
        foreach ([null, '{broken', '{"version":1,"revision":-1,"state":{}}'] as $invalid) {
            $this->connection->executeStatement('UPDATE simulations SET overview_state = ? WHERE id = ?', [$invalid, 'alpha']);
            self::assertSame($overview, $this->database->pageWorkspace('alpha', false));
        }
        $next = new SimulationEngine()->apply($source['state'], 'worker.availability', ['citizenId' => 'u1', 'available' => false, 'optIn' => false], bin2hex(random_bytes(16)));
        $this->database->save('alpha', 0, $next);
        $updated = $this->database->pageWorkspace('alpha', false);
        self::assertSame(1, $updated['revision']);
        self::assertFalse($updated['state']['workers'][0]['available']);
        self::assertSame($next, $this->database->workspace('alpha')['state']);
        $this->connection->executeStatement('UPDATE simulations SET state = ? WHERE id = ?', [json_encode($source['state']), 'alpha']);
        self::assertNull($this->connection->fetchOne('SELECT overview_state FROM simulations WHERE id = ?', ['alpha']));
        self::assertTrue($this->database->pageWorkspace('alpha', false)['state']['workers'][0]['available']);
        $this->database->workspace('beta');
        self::assertSame(0, $this->database->pageWorkspace('beta', false)['revision']);
    }

    public function testIncrementalProjectionMatchesRebuildAndLeavesUnchangedVotesUntouched(): void
    {
        $workspace = $this->database->workspace('alpha');
        $this->connection->executeStatement('CREATE TABLE projection_touches (n INTEGER)');
        $this->connection->executeStatement('INSERT INTO projection_touches VALUES (0)');
        foreach (['INSERT', 'DELETE'] as $operation) {
            $this->connection->executeStatement('CREATE TRIGGER watch_votes_' . $operation . ' AFTER ' . $operation . ' ON vote_records BEGIN UPDATE projection_touches SET n = n + 1; END');
        }
        $next = new SimulationEngine()->apply($workspace['state'], 'worker.availability', ['citizenId' => 'u1', 'available' => false, 'optIn' => false], bin2hex(random_bytes(16)));
        $this->database->save('alpha', 0, $next);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT n FROM projection_touches'));
        $projection = new RelationalProjection($this->connection);
        $changed = $next;
        array_shift($changed['votes']);
        $changed['votes'][0]['choice'] = 'pour';
        $changed['proposals'][0]['nodes'][0]['label'] = 'Nouvelle observation de test';
        $changed['proposals'][0]['revisions'] = [];
        $changed['experiments'] = [];
        $projection->write('alpha', $changed, $next);
        $tables = ['citizen_records', 'proposal_records', 'consultation_records', 'vote_records', 'experiment_records', 'audit_event_records', 'moderation_records', 'appeal_records', 'finance_records', 'rule_set_records', 'proposal_revision_records', 'causal_node_records', 'causal_edge_records', 'observation_records'];
        $actual = [];
        foreach ($tables as $table) {
            $actual[$table] = $this->connection->fetchAllAssociative('SELECT * FROM ' . $table . ' WHERE scenario_id = ? ORDER BY object_id', ['alpha']);
        }
        $projection->write('alpha', $changed);
        foreach ($tables as $table) {
            self::assertSame($actual[$table], $this->connection->fetchAllAssociative('SELECT * FROM ' . $table . ' WHERE scenario_id = ? ORDER BY object_id', ['alpha']));
        }
    }

    public function testCanonicalEncodingKeepsEveryHistoricalDigestByteForByte(): void
    {
        // Historical implementation is the independent compatibility oracle for signed archives.
        $old = static function (mixed $value) use (&$old): string {
            if (!is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
            if (array_is_list($value)) {
                return '[' . implode(',', array_map($old, $value)) . ']';
            }
            ksort($value, SORT_STRING);
            $parts = [];
            foreach ($value as $key => $item) {
                $parts[] = $old((string) $key) . ':' . $old($item);
            }

            return '{' . implode(',', $parts) . '}';
        };
        $engine = new SimulationEngine();
        $cases = [null, true, false, 1, 1.5, -0.0, 'é / < > " \n', [], ['b' => 1, 'a' => 2], [1 => 'one', 0 => 'zero'], ['01' => 'leading', 0 => 'zero'], [2 => ['b' => [false, null], 'a' => []]], ['nested' => (object) ['b' => 2, 'a' => 1]]];
        for ($i = 0; $i < 100; ++$i) {
            $cases[] = ['z' => [$i, $i / 7, ['é' => str_repeat('x/', $i), '0' => false]], 'a' => [1 => 'one', 0 => 'zero'], $i + 3 => null];
        }
        $cases[] = $this->database->workspace('alpha')['state'];
        foreach ($cases as $value) {
            self::assertSame(hash('sha256', $old($value)), $engine->digest($value));
        }
    }

    public function testWindowsKeepEveryItemOnceAndClampUntrustedPageNumbers(): void
    {
        $items = range(1, 135);
        $all = [];
        for ($page = 1; $page <= 5; ++$page) {
            $window = App\Service\PageWindow::slice($items, $page, 27);
            self::assertSame(135, $window['total']);
            self::assertSame(5, $window['pages']);
            $all = array_merge($all, $window['items']);
        }
        self::assertSame($items, $all);
        self::assertSame(1, App\Service\PageWindow::slice($items, PHP_INT_MIN, 27)['page']);
        self::assertSame(5, App\Service\PageWindow::slice($items, PHP_INT_MAX, 27)['page']);
        self::assertSame([], App\Service\PageWindow::slice([], 8, 30)['items']);
    }

    public function testIndexedBalancesPreserveAllSourceRowsOrderAndProvenance(): void
    {
        $indexed = new PublicBudget(dirname(__DIR__));
        $directory =
            dirname($this->path) . '/public-source-' . bin2hex(random_bytes(6));
        mkdir($directory . '/data/research', 0700, true);
        $path = $directory . '/data/research/municipal-balances-2025.json.gz';
        copy(
            dirname(__DIR__) . '/data/research/municipal-balances-2025.json.gz',
            $path,
        );
        $original = new PublicBudget($directory);
        try {
            foreach (['75056', '13055', '76351'] as $code) {
                foreach (['', '6', '10', '99999999'] as $prefix) {
                    foreach (
                        range(
                            1,
                            max(
                                2,
                                (int) ceil(
                                    $original->ledger($code, $prefix, 1)[
                                        'total'
                                    ] / 50,
                                ),
                            ),
                        ) as $page
                    ) {
                        self::assertSame(
                            $original->ledger($code, $prefix, $page),
                            $indexed->ledger($code, $prefix, $page),
                        );
                    }
                }
            }
            $indexPath =
                $directory . '/data/research/public-balances-index.sqlite';
            copy(
                dirname(__DIR__) .
                    '/data/research/public-balances-index.sqlite',
                $indexPath,
            );
            $pdo = new PDO('sqlite:' . $indexPath);
            $pdo->exec("UPDATE metadata SET source_hash = 'invalid'");
            $pdo = null;
            self::assertSame(
                $original->ledger('75056', '', 1),
                new PublicBudget($directory)->ledger('75056', '', 1),
            );
        } finally {
            if (isset($indexPath) && is_file($indexPath)) {
                unlink($indexPath);
            }
            unlink($path);
            rmdir(dirname($path));
            rmdir($directory . '/data');
            rmdir($directory);
        }
    }
}
