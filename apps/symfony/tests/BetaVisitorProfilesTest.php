<?php

declare(strict_types=1);

use App\Service\BetaVisitorProfiles;
use App\Service\GeographyCatalog;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class BetaVisitorProfilesTest extends TestCase
{
    public function testIpOnlyWithoutCookieAndWithoutPrivilege(): void
    {
        $root = sys_get_temp_dir() . '/populorum-beta-' . bin2hex(random_bytes(8));
        $dir = $root . '/data/geography';
        self::assertTrue(mkdir($dir, 0700, true));
        $geo = $dir . '/communes-2026.sqlite';
        $geodb = new PDO('sqlite:' . $geo);
        $geodb->exec("
            CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL);
            INSERT INTO metadata VALUES ('vintage','2026');
            CREATE TABLE communes (
                code TEXT PRIMARY KEY, name TEXT, region_code TEXT, region_name TEXT,
                department_code TEXT, department_name TEXT
            );
            INSERT INTO communes VALUES ('66134','Passa','76','Occitanie','66','Pyrénées-Orientales');
        ");
        $geodb = null;
        $database = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $database->executeStatement(
            'CREATE TABLE beta_visitor_profiles (
                ip_fingerprint VARCHAR(64) PRIMARY KEY,
                first_name VARCHAR(80), last_name VARCHAR(80), age INTEGER,
                commune_code VARCHAR(5), created_at VARCHAR(20), expires_at INTEGER
            )',
        );
        try {
            $service = new BetaVisitorProfiles($database, new GeographyCatalog($root), 'test-secret-not-for-deployment');
            $visitor = Request::create('https://example.test/compte-test', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.23']);
            $sameAddress = Request::create('https://example.test/compte-test', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.23']);
            $differentAddress = Request::create('https://example.test/compte-test', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.24']);
            self::assertNull($service->find($visitor));
            $created = $service->create($visitor, 'Alex', 'Exemple', '30', '66134');
            self::assertSame('Passa', $created['communeName']);
            self::assertSame($created, $service->find($sameAddress));
            self::assertNull($service->find($differentAddress));
            $unchanged = $service->create($sameAddress, 'Autre', 'Personnage', '44', '66134');
            self::assertSame('Alex', $unchanged['firstName']);
            $stored = $database->fetchOne('SELECT ip_fingerprint FROM beta_visitor_profiles');
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $stored);
            self::assertNotSame('198.51.100.23', $stored);
            self::assertSame(1, (int) $database->fetchOne('SELECT COUNT(*) FROM beta_visitor_profiles'));
            $token = $service->formToken($visitor, 'create');
            self::assertTrue($service->validFormToken($sameAddress, 'create', $token));
            self::assertFalse($service->validFormToken($differentAddress, 'create', $token));
            self::assertFalse($service->validFormToken($visitor, 'delete', $token));
            $service->delete($visitor);
            self::assertNull($service->find($visitor));
        } finally {
            $database->close();
            @unlink($geo);
            @rmdir($dir);
            @rmdir($root . '/data');
            @rmdir($root);
        }
    }

    public function testBadInputIsRejected(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $service = new BetaVisitorProfiles($db, new GeographyCatalog(sys_get_temp_dir()), 'test-secret');
        $visitor = Request::create('https://example.test/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.11']);
        foreach ([['', 'Nom', '30', '66134'], ['Prénom', 'Nom', '999', '66134'], ['Prénom', 'Nom', '25', '99999']] as $invalid) {
            try {
                $service->create($visitor, ...$invalid);
                self::fail('Invalid profile input accepted.');
            } catch (\DomainException) {
                self::assertTrue(true);
            }
        }
        $db->close();
    }
}
