<?php

declare(strict_types=1);

use App\Service\PolicyCatalog;
use App\Service\PolicyDeliberation;
use PHPUnit\Framework\TestCase;

final class PolicyDeliberationTest extends TestCase
{
    private array $roots = [];

    protected function tearDown(): void
    {
        foreach ($this->roots as $root) {
            foreach (['catalog.sqlite', 'catalog.sqlite-wal', 'catalog.sqlite-shm'] as $name) {
                @unlink($root . '/var/' . $name);
            }
            @rmdir($root . '/var');
            @rmdir($root);
        }
    }

    private function setupCatalog(): array
    {
        $root = sys_get_temp_dir() . '/populorum-causal-' . bin2hex(random_bytes(7));
        self::assertTrue(mkdir($root . '/var', 0700, true));
        $this->roots[] = $root;
        $db = new PDO('sqlite:' . $root . '/var/policy-catalog.sqlite');
        $db->exec(
            'CREATE TABLE policy_measures (
                id TEXT PRIMARY KEY, canonical_key TEXT UNIQUE, label TEXT,
                category TEXT, territory_code TEXT, source_type TEXT,
                source_url TEXT, source_date TEXT, source_reference TEXT,
                origin_actor TEXT, legal_status TEXT, review_status TEXT,
                status TEXT, closes_at TEXT
            );
            CREATE TABLE policy_votes (
                measure_id TEXT, citizen_id TEXT, choice TEXT, argument TEXT,
                updated_at TEXT, PRIMARY KEY (measure_id, citizen_id)
            );
            CREATE TABLE policy_vote_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT, measure_id TEXT,
                citizen_id TEXT, previous_choice TEXT, choice TEXT, updated_at TEXT
            );',
        );
        $insert = $db->prepare(
            'INSERT INTO policy_measures
             (id, canonical_key, label, category, territory_code, source_type,
              source_url, source_date, source_reference, origin_actor,
              legal_status, review_status, status, closes_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        foreach (['f', 'e'] as $suffix) {
            $insert->execute([
                'pm_' . str_repeat($suffix, 24), str_repeat($suffix, 64),
                'Réformer les politiques publiques de santé',
                'Santé', 'FR', 'proposition_de_loi',
                'https://www.senat.fr/dossier-legislatif/ppl26-450.html',
                '2026-11-03', 'Dépôt 450', 'Auteur officiel masqué',
                'En première lecture', 'titre original', 'open',
                gmdate('Y-m-d\TH:i:s+00:00', time() + 86400),
            ]);
        }

        return [new PolicyCatalog($root), new PolicyDeliberation($root), $db];
    }

    private function profile(string $letter = 'a'): array
    {
        return [
            'citizenId' => 'beta_' . str_repeat($letter, 24),
            'communeCode' => '66134',
            'expiresAt' => time() + 86400,
            'firstName' => 'Profil',
            'lastName' => 'DeTest',
        ];
    }

    private function causalPayload(): array
    {
        return [
            'parentId' => '',
            'causeText' => 'Le délai observé dans un service public nécessite une étude vérifiable.',
            'causeDegree' => 'probable',
            'causeSource' => '',
            'consequenceText' => 'La mise en œuvre du dispositif pourrait modifier le délai de traitement.',
            'consequenceDegree' => 'probable',
            'consequenceSource' => '',
        ];
    }

    public function testNoCausalSubmissionBeforeBallotAndNoAnonymityLeak(): void
    {
        [$catalog, $tree] = $this->setupCatalog();
        $id = 'pm_' . str_repeat('f', 24);
        self::assertSame(0, $tree->page($id, false)['total']);
        try {
            $tree->add($id, $this->profile(), $this->causalPayload());
            self::fail('Unvoted account was allowed to deliberate.');
        } catch (DomainException $error) {
            self::assertSame(403, $error->getCode());
        }

        $catalog->vote($id, $this->profile(), 'abstention');
        $entryId = $tree->add($id, $this->profile(), $this->causalPayload());
        self::assertMatchesRegularExpression('/^pc_[a-f0-9]{24}$/', $entryId);
        $rows = $tree->page($id, true);
        self::assertSame(1, $rows['total']);
        self::assertSame(0, $rows['rows'][0]['depth']);
        self::assertSame('Compte de test aaaaaaaa', $rows['rows'][0]['participant_label']);
        self::assertArrayNotHasKey('citizen_id', $rows['rows'][0]);
        self::assertArrayNotHasKey('firstName', $rows['rows'][0]);
        self::assertSame(0, $tree->page($id, false)['total']);
        self::assertNull($tree->entry($id, 'pc_' . str_repeat('0', 24)));
    }

    public function testDocumentedClaimRequiresValidHttpsSourceAndBothStatements(): void
    {
        [$catalog, $tree] = $this->setupCatalog();
        $id = 'pm_' . str_repeat('f', 24);
        $catalog->vote($id, $this->profile(), 'pour');
        foreach ([
            ['causeText' => ''],
            ['causeDegree' => 'demontree'],
            ['consequenceDegree' => 'demontree'],
            ['causeSource' => 'javascript:alert(1)'],
            ['causeSource' => 'https://user@example.org/resource'],
            ['consequenceText' => 'trop court'],
            ['causeDegree' => 'démontrée'],
        ] as $override) {
            try {
                $tree->add($id, $this->profile(), $override + array_diff_key(
                    $this->causalPayload(), $override,
                ));
                self::fail('Incomplete or unsafe causal data was accepted.');
            } catch (DomainException $error) {
                self::assertSame(400, $error->getCode());
            }
        }
        $payload = $this->causalPayload();
        $payload['causeDegree'] = 'demontree';
        $payload['causeSource'] = 'https://www.senat.fr/dossier-legislatif/ppl26-450.html';
        $entryId = $tree->add($id, $this->profile(), $payload);
        self::assertSame('demontree', $tree->page($id, true)['rows'][0]['cause_degree']);
        self::assertSame($entryId, $tree->entry($id, $entryId)['id']);
    }

    public function testParentBranchesAreLinkedToTheSameOfficialDossier(): void
    {
        [$catalog, $tree] = $this->setupCatalog();
        $a = 'pm_' . str_repeat('f', 24);
        $b = 'pm_' . str_repeat('e', 24);
        $catalog->vote($a, $this->profile(), 'pour');
        $catalog->vote($b, $this->profile(), 'contre');
        $parentId = $tree->add($a, $this->profile(), $this->causalPayload());
        try {
            $tree->add($b, $this->profile(), [
                'parentId' => $parentId,
            ] + $this->causalPayload());
            self::fail('Cross-proposal reply accepted.');
        } catch (DomainException $error) {
            self::assertSame(400, $error->getCode());
        }
        $child = $tree->add($a, $this->profile(), [
            'parentId' => $parentId,
        ] + $this->causalPayload());
        $entry = $tree->page($a, true)['rows'][0];
        self::assertSame($child, $entry['id']);
        self::assertSame($parentId, $entry['parent_id']);
        self::assertSame(1, $entry['depth']);
        self::assertNotEmpty($entry['parent_cause']);
    }

    public function testCausalAnalysisCanContinueAfterVotingClosesWithoutChangingBallots(): void
    {
        [$catalog, $tree, $db] = $this->setupCatalog();
        $id = 'pm_' . str_repeat('f', 24);
        $catalog->vote($id, $this->profile(), 'contre');
        $db->exec("UPDATE policy_measures SET status='closed' WHERE id = '" . $id . "'");
        $tree->add($id, $this->profile(), $this->causalPayload());
        self::assertSame('contre', $catalog->existingVote($id, $this->profile()));
        self::assertSame(1, $tree->page($id, true)['total']);
        self::assertSame(
            1, (int) $db->query('SELECT COUNT(*) FROM policy_votes')->fetchColumn(),
        );
        self::assertSame(
            1, (int) $db->query('SELECT COUNT(*) FROM policy_vote_history')->fetchColumn(),
        );
        self::assertTrue($catalog->measure($id)['closed']);
    }
}
