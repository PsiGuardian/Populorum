<?php

declare(strict_types=1);

use App\Service\BudgetCatalog;
use App\Service\GeographyCatalog;
use App\Service\PublicBudget;
use App\Service\PublicProcurementData;
use PHPUnit\Framework\TestCase;

final class SqlInputSecurityTest extends TestCase
{
    private const ATTACKS = [
        "' OR 1=1 --",
        "'; DROP TABLE contracts; --",
        "' UNION SELECT name FROM sqlite_master --",
        '" OR "x"="x',
        "x' /* comment */",
        '%_!',
        '") OR (1=1',
        "x\nUNION SELECT 1",
        "x\0y",
    ];

    private function root(): string
    {
        $root = sys_get_temp_dir() . '/populorum-sql-' . bin2hex(random_bytes(8));
        mkdir($root . '/data/research', 0700, true);
        mkdir($root . '/data/geography', 0700, true);

        return $root;
    }

    private function cleanup(string $root): void
    {
        @unlink($root . '/data/research/public-procurement.sqlite');
        @unlink($root . '/data/geography/communes-2026.sqlite');
        @rmdir($root . '/data/research');
        @rmdir($root . '/data/geography');
        @rmdir($root . '/data');
        @rmdir($root);
    }

    public function testPublicProcurementFiltersAreReadOnlyAndBound(): void
    {
        $root = $this->root();
        try {
            $file = $root . '/data/research/public-procurement.sqlite';
            $db = new PDO('sqlite:' . $file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $db->exec(<<<'SQL'
CREATE TABLE metadata(key TEXT PRIMARY KEY, value TEXT);
INSERT INTO metadata VALUES ('schema','populorum-decp-2');
CREATE TABLE contracts(contract_key TEXT PRIMARY KEY, kind TEXT, buyer_id TEXT,
 original_id TEXT, object TEXT, cpv TEXT, nature TEXT, notice_date TEXT,
 amount_initial_cents INTEGER, amount_amended_cents INTEGER,
 location_type TEXT, location_code TEXT, source_name TEXT, source_sha TEXT);
CREATE TABLE participants(contract_key TEXT, role TEXT, identifier_type TEXT, identifier TEXT);
CREATE TABLE changes(contract_key TEXT, event_type TEXT, event_id TEXT,
 event_date TEXT, published_date TEXT, amount_cents INTEGER, identifier TEXT, extra TEXT);
CREATE TABLE imports(sha256 TEXT, filename TEXT, source_url TEXT, imported_at TEXT);
CREATE TABLE contract_sources(contract_key TEXT, source_sha TEXT);
CREATE TABLE contract_records(source_sha TEXT,kind TEXT,ordinal INTEGER,
 contract_key TEXT,record_sha TEXT,record_zlib BLOB);
CREATE TABLE procurement_evidence(evidence_id TEXT,contract_key TEXT,category TEXT,
 source_type TEXT,source_name TEXT,source_url TEXT,document_title TEXT,
 statement TEXT,excerpt TEXT,published_at TEXT,recorded_at TEXT,status TEXT);
CREATE TABLE concession_finance(contract_key TEXT, subsidy_cents INTEGER);
CREATE VIRTUAL TABLE contract_fts USING fts5(
 object, original_id, content='contracts', content_rowid='rowid');
SQL);
            $insert = $db->prepare('INSERT INTO contracts VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $insert->execute([str_repeat('a', 40), 'market', '21660134400013', 'A-2025',
                'Éclairage de Passa', '12345000', 'Marché', '2025-03-01', 50000, null,
                'Code commune', '66134', 'fixture', 'fixture-sha']);
            $insert->execute([str_repeat('b', 40), 'market', '21750560000013', 'B-2025',
                'Éclairage de Paris', '12345000', 'Marché', '2025-04-01', 80000, null,
                'Code commune', '75056', 'fixture', 'fixture-sha']);
            $insert->execute([str_repeat('c', 40), 'concession', '21660134400013', 'C-2024',
                'Transport départemental', '67890000', 'Concession', '2024-03-01', 120000, null,
                'Code département', '66', 'fixture', 'fixture-sha']);
            $db->exec("INSERT INTO imports VALUES ('fixture-sha','decp-2024-04.json','https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides','2026-11-05')");
            $supplier = $db->prepare('INSERT INTO participants VALUES (?, ?, ?, ?)');
            $supplier->execute([str_repeat('a', 40), 'supplier', 'SIRET', '12345678900011']);
            $db->exec("INSERT INTO contract_fts(contract_fts) VALUES('rebuild')");
            $db = null;
            $digest = hash_file('sha256', $file);
            $service = new PublicProcurementData($root);

            self::assertSame(3, $service->coverage()['contracts']);
            $market = $service->search(['kind' => 'market'])['items'][0];
            self::assertSame('fixture-sha', $market['source_sha']);
            self::assertSame('decp-2024-04.json', $market['source_filename']);
            self::assertStringStartsWith('https://www.data.gouv.fr/', $market['source_url']);
            self::assertSame(1, $service->cityCount('66134'));
            self::assertSame(2, $service->search(['kind' => 'market'])['total']);
            self::assertSame(2, $service->search(['year' => '2025'])['total']);
            self::assertSame(2, $service->search(['buyer' => '216601344'])['total']);
            self::assertSame(1, $service->search(['supplier' => '123456789'])['total']);
            self::assertSame(1, $service->search(['city' => '66134'])['total']);
            self::assertSame(1, $service->search(['department' => '66'])['total']);
            self::assertSame(1, $service->search(['q' => 'Éclairage Passa'])['total']);
            self::assertSame(2, $service->budgetConcordance('FR', '2025')['contractsInYear']);
            self::assertSame(1, $service->budgetConcordance('66134', '2025')['contractsInYear']);
            self::assertNotNull($service->detail(str_repeat('a', 40)));
            self::assertNotNull($service->detail(str_repeat('c', 40)));
            self::assertCount(1, $service->years('market'));

            $expectedKeys = [str_repeat('a', 40), str_repeat('b', 40), str_repeat('c', 40)];
            foreach (self::ATTACKS as $attack) {
                foreach (['kind', 'year', 'buyer', 'supplier', 'city', 'department', 'q'] as $field) {
                    $result = $service->search([$field => $attack], -10, 1000);
                    foreach ($result['items'] as $item) {
                        self::assertContains($item['contract_key'], $expectedKeys, $field);
                    }
                    self::assertLessThanOrEqual(3, $result['total'], $field);
                }
                foreach ($service->search(['city' => '66134', 'q' => $attack])['items'] as $item) {
                    self::assertSame('66134', $item['location_code']);
                }
                self::assertSame(0, $service->cityCount($attack));
                self::assertSame([], $service->years($attack));
                self::assertNull($service->detail($attack));
                self::assertNull($service->budgetConcordance($attack, '2025')['contractsAllYears']);
                self::assertLessThanOrEqual(1, $service->budgetConcordance('66134', $attack)['contractsInYear']);
            }
            self::assertSame(3, $service->search([], 0, 1000)['total']);
            self::assertSame($digest, hash_file('sha256', $file), 'Unexpected DECP write');
        } finally {
            $this->cleanup($root);
        }
    }

    public function testGeographicQueriesDoNotInterpretHostileTerms(): void
    {
        $root = $this->root();
        try {
            $file = $root . '/data/geography/communes-2026.sqlite';
            $db = new PDO('sqlite:' . $file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $db->exec(<<<'SQL'
CREATE TABLE metadata(key TEXT PRIMARY KEY, value TEXT);
INSERT INTO metadata VALUES ('vintage','2026');
CREATE TABLE communes(code TEXT PRIMARY KEY, name TEXT, search_name TEXT,
 region_code TEXT, region_name TEXT, department_code TEXT, department_name TEXT);
INSERT INTO communes VALUES ('66134','Passa','passa','76','Occitanie','66','Pyrénées-Orientales');
INSERT INTO communes VALUES ('75056','Paris','paris','11','Île-de-France','75','Paris');
CREATE TABLE postal_codes(code TEXT, postal_code TEXT);
INSERT INTO postal_codes VALUES ('66134','66300');
SQL);
            $db = null;
            $digest = hash_file('sha256', $file);
            $service = new GeographyCatalog($root);

            self::assertSame('Passa', $service->find('66134')['name']);
            self::assertCount(1, $service->search('Passa'));
            self::assertCount(1, $service->search('INSEE:66134'));
            self::assertCount(1, $service->search('66300'));
            self::assertSame(1, $service->cityPage('76', '66', 0)['total']);
            foreach (self::ATTACKS as $attack) {
                self::assertNull($service->find($attack));
                self::assertSame([], $service->departments($attack));
                self::assertSame(0, $service->cityPage($attack, '66', -10, 1000)['total']);
                self::assertSame(0, $service->cityPage('76', $attack, -10, 1000)['total']);
                foreach ($service->search($attack, 1000, -10) as $row) {
                    self::assertContains($row['code'], ['66134', '75056']);
                }
            }
            self::assertSame($digest, hash_file('sha256', $file), 'Unexpected geography write');
        } finally {
            $this->cleanup($root);
        }
    }

    public function testMunicipalBudgetCodeCannotChangeSqlScope(): void
    {
        $root = $this->root();
        try {
            $file = $root . '/data/research/municipal-national-2025.sqlite';
            $db = new PDO('sqlite:' . $file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $db->exec(<<<'SQL'
CREATE TABLE metadata(key TEXT PRIMARY KEY, value TEXT);
INSERT INTO metadata VALUES ('year','2025');
CREATE TABLE municipalities(code TEXT PRIMARY KEY, name TEXT, region_code TEXT,
 region_name TEXT, department_code TEXT, department_name TEXT, population INTEGER);
CREATE TABLE aggregates(code TEXT, aggregate TEXT, amount_cents INTEGER,
 main_cents INTEGER, annex_cents INTEGER, cross_cents INTEGER);
INSERT INTO municipalities VALUES ('66134','Passa','76','Occitanie','66','Pyrénées-Orientales',1200);
INSERT INTO aggregates VALUES ('66134','Dépenses totales',80000,80000,0,0);
SQL);
            $db = null;
            $digest = hash_file('sha256', $file);
            $catalog = new BudgetCatalog($root, new PublicBudget($root), new GeographyCatalog($root));
            self::assertSame(1, $catalog->coverage()['cities']);
            self::assertSame('66134', $catalog->city('66134')['city']['code']);
            foreach (self::ATTACKS as $attack) {
                self::assertNull($catalog->city($attack));
            }
            self::assertSame($digest, hash_file('sha256', $file), 'Unexpected budget write');
        } finally {
            @unlink($root . '/data/research/municipal-national-2025.sqlite');
            $this->cleanup($root);
        }
    }
}
