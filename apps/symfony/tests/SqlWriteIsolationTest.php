<?php

declare(strict_types=1);

use App\Security\CivicAccountProvider;
use App\Service\DemoDatabase;
use App\Service\RelationalProjection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/** Tests SQL value binding and scenario isolation without contacting production. */
final class SqlWriteIsolationTest extends TestCase
{
    private const ATTACKS = [
        "' OR 1=1 --",
        "'; DROP TABLE simulations; --",
        "' UNION SELECT username FROM civic_accounts --",
        "x' /* comment */",
        '" OR "x"="x',
        "x\0y",
    ];

    private function db(): \Doctrine\DBAL\Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    public function testCivicAccountLookupNeverInterpretsIdentifierAsSql(): void
    {
        $db = $this->db();
        try {
            $db->executeStatement(
                'CREATE TABLE civic_accounts (username TEXT PRIMARY KEY, password_hash TEXT, workspace_id TEXT, citizen_id TEXT, roles TEXT, enabled INTEGER)',
            );
            foreach (['alice', 'bob'] as $username) {
                $db->insert('civic_accounts', [
                    'username' => $username,
                    'password_hash' => 'test-only-hash',
                    'workspace_id' => 'workspace-' . $username,
                    'citizen_id' => 'u1',
                    'roles' => '["ROLE_CITIZEN"]',
                    'enabled' => 1,
                ]);
            }
            $provider = new CivicAccountProvider($db);
            self::assertSame('alice', $provider->loadUserByIdentifier('alice')->getUserIdentifier());
            foreach (self::ATTACKS as $attack) {
                try {
                    $provider->loadUserByIdentifier($attack);
                    self::fail('Unexpected account access');
                } catch (UserNotFoundException) {
                    self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM civic_accounts'));
                }
            }
            self::assertSame('bob', $provider->loadUserByIdentifier('bob')->getUserIdentifier());
        } finally {
            $db->close();
        }
    }

    public function testScenarioWritesStayWithinOwnerAndRevision(): void
    {
        $db = $this->db();
        try {
            $db->executeStatement(
                'CREATE TABLE simulations (id TEXT PRIMARY KEY, revision INTEGER, state TEXT, page_state TEXT, overview_state TEXT, updated_at TEXT)',
            );
            $db->executeStatement(
                'CREATE TABLE proposal_records (scenario_id TEXT, object_id TEXT, territory_id TEXT, parent_id TEXT, version INTEGER, data TEXT, PRIMARY KEY(scenario_id, object_id))',
            );
            $db->executeStatement(
                'CREATE TABLE audit_outbox (event_id TEXT, scenario_id TEXT, dispatched INTEGER)',
            );
            $initial = [
                'citizens' => [], 'proposals' => [], 'consultations' => [], 'votes' => [],
                'experiments' => [], 'events' => [], 'cases' => [], 'appeals' => [],
                'finance' => [], 'ruleSets' => [],
            ];
            foreach (['owner-a', 'owner-b'] as $owner) {
                $db->insert('simulations', [
                    'id' => $owner, 'revision' => 0,
                    'state' => json_encode($initial, JSON_THROW_ON_ERROR),
                    'page_state' => null, 'overview_state' => null,
                    'updated_at' => gmdate('c'),
                ]);
            }
            $service = new DemoDatabase($db, dirname(__DIR__), new RelationalProjection($db));
            foreach (self::ATTACKS as $attack) {
                try {
                    $service->save($attack, 0, $initial);
                    self::fail('Unexpected cross-owner mutation');
                } catch (\DomainException $e) {
                    self::assertSame(409, $e->getCode());
                }
            }
            $updated = $initial;
            $updated['notes'] = "' OR 1=1 --";
            $updated['proposals'] = [[
                'id' => "'; DROP TABLE simulations; --",
                'territoryId' => 't1',
                'revisions' => [], 'nodes' => [], 'edges' => [],
            ]];
            $service->save('owner-a', 0, $updated);
            self::assertSame(1, (int) $db->fetchOne('SELECT revision FROM simulations WHERE id = ?', ['owner-a']));
            self::assertSame(0, (int) $db->fetchOne('SELECT revision FROM simulations WHERE id = ?', ['owner-b']));
            self::assertSame(json_encode($initial, JSON_THROW_ON_ERROR), $db->fetchOne('SELECT state FROM simulations WHERE id = ?', ['owner-b']));
            self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM simulations'));
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM proposal_records'));
            self::assertSame("'; DROP TABLE simulations; --", $db->fetchOne('SELECT object_id FROM proposal_records'));
            self::assertSame('owner-a', $db->fetchOne('SELECT scenario_id FROM proposal_records'));
            self::assertSame(1, $service->pageWorkspace('owner-a', false)['revision']);
            try {
                $service->save('owner-a', 0, $updated);
                self::fail('Stale revisions must be rejected');
            } catch (\DomainException $e) {
                self::assertSame(409, $e->getCode());
            }
            self::assertSame(0, (int) $db->fetchOne('SELECT revision FROM simulations WHERE id = ?', ['owner-b']));
            self::assertSame(1, (int) $db->fetchOne('SELECT revision FROM simulations WHERE id = ?', ['owner-a']));
        } finally {
            $db->close();
        }
    }
}
