<?php

declare(strict_types=1);

use App\Service\PublicProcurementData;
use App\Twig\ProcurementMoneyExtension;
use PHPUnit\Framework\TestCase;

final class ProcurementDossierTest extends TestCase
{
    private string $root;
    private string $national;
    private string $sidecar;
    private string $automatic;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/populorum-procurement-dossier-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root . '/data/research', 0700, true));
        $this->national = $this->root . '/data/research/public-procurement.sqlite';
        $this->sidecar = $this->root . '/data/research/procurement-financial-evidence.sqlite';
        $this->automatic = $this->root . '/data/research/procurement-auto-concordance.sqlite';
    }

    protected function tearDown(): void
    {
        @unlink($this->sidecar);
        @unlink($this->automatic);
        @unlink($this->national);
        @rmdir($this->root . '/data/research');
        @rmdir($this->root . '/data');
        @rmdir($this->root);
    }

    public function testDossierDisclosesMethodErrorsAndPossibleDgfipAccess(): void
    {
        $template = file_get_contents(__DIR__ . '/../templates/partials/procurement-dossier.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString('Origine des données, algorithme et limites', $template);
        self::assertStringContainsString('35 %', $template);
        self::assertStringContainsString('indice de Jaccard', $template);
        self::assertStringContainsString('des erreurs de rapprochement', $template);
        self::assertStringContainsString('Hélios / PES V2', $template);
        self::assertStringContainsString('convention', $template);
        self::assertStringContainsString('aucune API publique nationale', $template);
        self::assertStringContainsString('https://www.impots.gouv.fr/comment-acceder-aux-api-de-la-dgfip', $template);
    }

    public function testBoampDecisionHistoryIsExplainedOnPublicDossier(): void
    {
        $template = file_get_contents(__DIR__ . '/../templates/partials/procurement-dossier.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString('Historique des décisions algorithmiques', $template);
        self::assertStringContainsString('algorithm_version', $template);
        self::assertStringContainsString('auto.scan_history', $template);
        self::assertStringContainsString('aucun avis retenu', $template);
        self::assertStringContainsString('factors_json', $template);
        self::assertStringContainsString('legacy_snapshot', $template);
        self::assertStringContainsString('les archives peuvent être incomplètes', $template);
    }

    public function testNoFloatingPointLossOnExtremeDeclaredAmounts(): void
    {
        self::assertSame('99 999 999 999 999,99 €', ProcurementMoneyExtension::formatCents('9999999999999999'));
        self::assertSame('99 999 999 999,00 €', ProcurementMoneyExtension::formatCents(9999999999900));
        self::assertSame('-1 234,50 €', ProcurementMoneyExtension::formatCents(-123450));
        self::assertSame('0,01 €', ProcurementMoneyExtension::formatCents(1));
        self::assertSame('Non communiqué', ProcurementMoneyExtension::formatCents(null));
    }

    public function testAmountFlagsAreNonDestructiveResearchHints(): void
    {
        self::assertSame([], PublicProcurementData::amountWarnings(40000000, 'Code commune', '76351'));
        $suspicious = PublicProcurementData::amountWarnings(9999999999900, 'Code postal', '38');
        self::assertCount(2, $suspicious);
        self::assertStringContainsString('chiffres 9', $suspicious[0]);
        self::assertStringContainsString('code postal', $suspicious[1]);
        self::assertNotEmpty(PublicProcurementData::amountWarnings(1000000000000));
        self::assertNotEmpty(PublicProcurementData::amountWarnings(-200));
    }

    public function testPublicationsAndPossibleRelatedLotsDoNotMultiplyOrMergeAmounts(): void
    {
        $db = new PDO('sqlite:' . $this->national, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec(<<<'SQL'
CREATE TABLE metadata(key TEXT PRIMARY KEY,value TEXT);
INSERT INTO metadata VALUES ('schema','populorum-decp-2');
CREATE TABLE contracts (
 contract_key TEXT PRIMARY KEY, kind TEXT,buyer_id TEXT,original_id TEXT,
 object TEXT,cpv TEXT,nature TEXT,notice_date TEXT,amount_initial_cents INTEGER,
 amount_amended_cents INTEGER,location_type TEXT,location_code TEXT,
 source_name TEXT,source_sha TEXT
);
CREATE INDEX buyer_test ON contracts(buyer_id,kind,notice_date);
CREATE INDEX contracts_buyer_reference ON contracts(buyer_id,kind,original_id,notice_date DESC,contract_key ASC);
CREATE TABLE imports(sha256 TEXT PRIMARY KEY,filename TEXT,source_url TEXT,imported_at TEXT);
CREATE TABLE contract_sources(contract_key TEXT,source_sha TEXT);
CREATE TABLE contract_publications(contract_key TEXT,source_sha TEXT,declared_publication_date TEXT,
 notice_date TEXT,base_amount_cents INTEGER,modification_amount_cents INTEGER,occurrences INTEGER);
CREATE TABLE changes(contract_key TEXT,event_type TEXT,event_id TEXT,event_date TEXT,
 published_date TEXT,amount_cents INTEGER,identifier TEXT,extra TEXT);
CREATE TABLE participants(contract_key TEXT,role TEXT,identifier_type TEXT,identifier TEXT);
CREATE TABLE procurement_evidence(contract_key TEXT,category TEXT,source_type TEXT,source_name TEXT,
 source_url TEXT,document_title TEXT,statement TEXT,excerpt TEXT,published_at TEXT,recorded_at TEXT,status TEXT);
CREATE TABLE contract_records(contract_key TEXT,source_sha TEXT,kind TEXT,ordinal INTEGER,record_sha TEXT,record_zlib BLOB);
CREATE TABLE concession_finance(contract_key TEXT,subsidy_cents INTEGER);
SQL);
        $a = str_repeat('a', 40);
        $b = str_repeat('b', 40);
        $c = str_repeat('c', 40);
        $stmt = $db->prepare('INSERT INTO contracts
            (contract_key,kind,buyer_id,original_id,object,cpv,nature,notice_date,
             amount_initial_cents,location_type,location_code,source_sha)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$a, 'market', '26380030200014', 'AO25DMISP', 'Lot 1 implant',
            '33140000', 'Marché', '2025-05-20', 9999999999900, 'Code postal', '38', 'first']);
        $stmt->execute([$b, 'market', '26380030200014', 'AO25DMISP', 'Lot 2 implant',
            '33140000', 'Marché', '2025-05-20', 50000000, 'Code département', '38', 'first']);
        $stmt->execute([$c, 'market', 'other-buyer', 'AO25DMISP', 'Autre acheteur',
            '33140000', 'Marché', '2025-05-20', 30000000, 'Code département', '38', 'first']);
        $db->exec(<<<'SQL'
INSERT INTO imports VALUES('first','decp-2025-06.json','https://example.org/first','2025-06-20');
INSERT INTO imports VALUES('second','decp-2026-01.json','https://example.org/second','2026-02-01');
SQL);
        $db->prepare('INSERT INTO contract_sources VALUES (?,?)')->execute([$a, 'first']);
        $db->prepare('INSERT INTO contract_sources VALUES (?,?)')->execute([$a, 'second']);
        $db->prepare('INSERT INTO contract_publications VALUES (?,?,?,?,?,?,?)')
            ->execute([$a, 'first', '2025-06-01', '2025-05-20', 9999999999900, null, 1]);
        $db->prepare('INSERT INTO contract_publications VALUES (?,?,?,?,?,?,?)')
            ->execute([$a, 'second', '2026-01-01', '2025-05-20', 9999999999900, null, 1]);
        $db = null;

        $financial = new PDO('sqlite:' . $this->sidecar, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $financial->exec(<<<'SQL'
CREATE TABLE metadata(key TEXT PRIMARY KEY,value TEXT);
INSERT INTO metadata VALUES ('schema','populorum-procurement-financial-1');
CREATE TABLE financial_evidence(
 contract_key TEXT,stage TEXT,amount_cents INTEGER,accounting_scope TEXT,as_of_date TEXT,
 period_start TEXT,period_end TEXT,document_date TEXT,source_issuer TEXT,
 document_title TEXT,source_url TEXT,source_excerpt TEXT,recorded_at TEXT
);
SQL);
        $financial->prepare('INSERT INTO financial_evidence VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$a, 'paid', 150000, 'cumulative', '2026-11-01', '', '', '2026-11-02',
                'Source test', 'Document test', 'https://example.org/source', 'Extrait test', '2026-11-02']);
        $financial = null;

        $boamp = new PDO('sqlite:' . $this->automatic, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $boamp->exec(<<<'SQL'
CREATE TABLE metadata(key TEXT PRIMARY KEY,value TEXT);
INSERT INTO metadata VALUES('schema','populorum-boamp-concordance-1');
CREATE TABLE checks(contract_key TEXT PRIMARY KEY,checked_at TEXT,status TEXT,result_count INTEGER,message TEXT);
CREATE TABLE reference_matches(contract_key TEXT,idweb TEXT,status TEXT,basis TEXT,
 title TEXT,url TEXT,publication_date TEXT,notice_kind TEXT,notice_sha256 TEXT,notice_total_eur TEXT,
 first_seen_at TEXT,last_seen_at TEXT);
SQL);
        $boamp->prepare('INSERT INTO checks VALUES (?,?,?,?,?)')
            ->execute([$a, '2026-11-12T15:00:00Z', 'ok', 1, '']);
        $boamp->prepare('INSERT INTO reference_matches VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$a, '26-123456', 'procedure_ids', 'Identifiants présents',
                'Avis test', 'https://www.boamp.fr/pages/avis/?q=idweb:26-123456',
                '2026-11-12', 'Résultat de marché', str_repeat('a', 64), '335600.00',
                '2026-11-12', '2026-11-12']);
        $boamp = null;

        $reader = new PublicProcurementData($this->root);
        $page = $reader->search([], 0);
        self::assertSame(3, $page['total'], 'Source publications must not multiply list rows');
        self::assertCount(3, $page['items']);
        $aResult = array_column($page['items'], null, 'contract_key')[$a];
        self::assertSame(2, (int) $aResult['source_count']);
        self::assertSame(9999999999900, (int) $aResult['amount_initial_cents']);

        $detail = $reader->detail($a);
        self::assertNotNull($detail);
        self::assertSame(2, $detail['history']['references']);
        self::assertSame(2, $detail['history']['observed']);
        self::assertTrue($detail['reference_relations']['indexed']);
        self::assertCount(1, $detail['reference_relations']['items']);
        self::assertSame($b, $detail['reference_relations']['items'][0]['contract_key']);
        self::assertSame('ok', $detail['automatic_concordance']['status']);
        self::assertCount(1, $detail['automatic_concordance']['confirmed']);
        self::assertSame('335600.00', $detail['automatic_concordance']['confirmed'][0]['notice_total_eur']);
        // Once the national BOAMP index has been matched locally, the
        // dossier must display its candidate without losing legacy
        // individually fetched documentary links.
        $localDb = new PDO('sqlite:' . $this->automatic, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $localDb->exec(<<<'SQL'
CREATE TABLE local_checks(contract_key TEXT,checked_at TEXT,
 scanned_notices INTEGER,matched_notices INTEGER,truncated INTEGER,
 source_revision TEXT,status TEXT);
CREATE TABLE local_matches(contract_key TEXT,idweb TEXT,notice_sha TEXT,status TEXT,
 basis TEXT,title TEXT,url TEXT,publication_date TEXT,notice_kind TEXT,
 first_seen_at TEXT,last_seen_at TEXT,active INTEGER);
SQL);
        $localDb->prepare('INSERT INTO local_checks VALUES (?,?,?,?,?,?,?)')
            ->execute([$a, '2026-11-12', 20, 1, 0, '2', 'checked']);
        $localDb->prepare('INSERT INTO local_matches VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$a, '25-92465', str_repeat('b', 64), 'candidate',
                'SIRET cité, intitulé proche ; lot à vérifier',
                'Avis distinct', 'https://www.boamp.fr/pages/avis/?q=idweb:25-92465',
                '2025-08-14', 'Résultat', '2026-11-12', '2026-11-12', 1]);
        $localDb = null;
        $localDetail = $reader->detail($a);
        self::assertSame('checked', $localDetail['automatic_concordance']['status']);
        self::assertSame('index_national_local', $localDetail['automatic_concordance']['source']);
        self::assertSame(20, $localDetail['automatic_concordance']['scanned_notices']);
        self::assertCount(1, $localDetail['automatic_concordance']['candidates']);
        self::assertCount(1, $localDetail['automatic_concordance']['confirmed']);
        self::assertSame('26-123456', $localDetail['automatic_concordance']['confirmed'][0]['idweb']);

        self::assertSame([], $detail['automatic_concordance']['candidates']);
        self::assertCount(1, $detail['financial_evidence']);
        self::assertSame('paid', $detail['financial_evidence'][0]['stage']);
        self::assertSame(150000, (int) $detail['financial_evidence'][0]['amount_cents']);
        // Related lots are not reclassified as multiple payments of the same contract.
        self::assertSame([], $reader->detail($b)['financial_evidence']);
        // Pre-upgrade production databases may lack the optional index.
        // The expensive relation lookup is deferred, never run as a table scan.
        $maintenance = new PDO('sqlite:' . $this->national);
        $maintenance->exec('DROP INDEX contracts_buyer_reference');
        $maintenance = null;
        $oldDetail = $reader->detail($a);
        self::assertFalse($oldDetail['reference_relations']['indexed']);
        self::assertSame([], $oldDetail['reference_relations']['items']);
        self::assertCount(2, $oldDetail['history']['items']);
        self::assertCount(1, $oldDetail['financial_evidence']);
    }
}
