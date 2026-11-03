<?php

declare(strict_types=1);

use App\Service\PolicyCatalog;
use PHPUnit\Framework\TestCase;

final class PolicyCatalogTest extends TestCase
{
    private array $locations = [];

    protected function tearDown(): void
    {
        foreach ($this->locations as $root) {
            foreach (['catalog.sqlite', 'catalog.sqlite-wal', 'catalog.sqlite-shm'] as $file) {
                @unlink($root . '/var/' . $file);
            }
            @rmdir($root . '/var');
            @rmdir($root);
        }
    }

    private function storage(string $territory = 'FR'): array
    {
        $root = sys_get_temp_dir() . '/populorum-measures-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root . '/var', 0700, true));
        $this->locations[] = $root;
        $path = $root . '/var/policy-catalog.sqlite';
        $db = new PDO('sqlite:' . $path);
        $db->exec(
            'CREATE TABLE policy_measures (
                id TEXT PRIMARY KEY, canonical_key TEXT UNIQUE, label TEXT,
                category TEXT, territory_code TEXT, status TEXT,
                closes_at TEXT, source_type TEXT, source_url TEXT,
                source_date TEXT, source_reference TEXT, origin_actor TEXT,
                legal_status TEXT, review_status TEXT
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
        $query = $db->prepare(
            'INSERT INTO policy_measures (
                id, canonical_key, label, category, territory_code, status,
                closes_at, source_type, source_url, source_date, source_reference,
                origin_actor, legal_status, review_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $query->execute([
            'pm_' . str_repeat('f', 24), str_repeat('f', 64), 'Créer un dispositif de test',
            'Éducation', $territory, 'open',
            gmdate('Y-m-d\TH:i:s+00:00', time() + 86400),
            'proposition_de_loi', 'https://www.senat.fr/dossier-legislatif/ppl26-450.html',
            '03/11/2026', 'Dépôt 450', 'Origine cachée tant que le vote est ouvert',
            'En première lecture', 'titre officiel',
        ]);

        return [new PolicyCatalog($root), $db];
    }

    private function profile(string $id = 'a', string $commune = '66134'): array
    {
        return [
            'citizenId' => 'beta_' . str_repeat($id, 24),
            'communeCode' => $commune,
            'expiresAt' => time() + 86400,
        ];
    }

    public function testParliamentaryAttributionIsPublicButVoteTallyWaitsForClosure(): void
    {
        [$catalog] = $this->storage();
        $page = $catalog->page();
        self::assertSame(1, $page['total']);
        self::assertCount(1, $page['rows']);
        $row = $page['rows'][0];
        self::assertFalse($row['closed']);
        self::assertSame('Origine cachée tant que le vote est ouvert', $row['originActor']);
        self::assertSame(
            'https://www.senat.fr/dossier-legislatif/ppl26-450.html', $row['sourceUrl'],
        );
        self::assertSame('En première lecture', $row['legalStatus']);
        self::assertArrayNotHasKey('tally', $row);
        self::assertSame(1, $catalog->page(999)['page']);
        self::assertSame(0, $catalog->page(1, 'aucune correspondance')['total']);
    }

    public function testOneActiveVotePerBetaFamilyWithRevisionsAndClose(): void
    {
        [$catalog, $db] = $this->storage();
        $id = 'pm_' . str_repeat('f', 24);
        $catalog->vote($id, $this->profile(), 'pour');
        $catalog->vote($id, $this->profile(), 'contre');
        $catalog->vote($id, $this->profile(), 'contre');
        $catalog->vote($id, $this->profile('b'), 'abstention');
        self::assertSame('contre', $catalog->existingVote($id, $this->profile()));
        self::assertSame(
            2, (int) $db->query('SELECT COUNT(*) FROM policy_votes')->fetchColumn(),
        );
        self::assertSame(
            3, (int) $db->query('SELECT COUNT(*) FROM policy_vote_history')->fetchColumn(),
        );
        $db->exec(
            "UPDATE policy_measures SET closes_at = '2026-01-01T00:00:00+00:00'",
        );
        $closed = $catalog->measure($id);
        self::assertTrue($closed['closed']);
        self::assertSame('Origine cachée tant que le vote est ouvert', $closed['originActor']);
        self::assertSame(['pour' => 0, 'contre' => 1, 'abstention' => 1], $closed['tally']);
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(409);
        $catalog->vote($id, $this->profile(), 'pour');
    }

    public function testUnstructuredNewBallotArgumentsAreRefused(): void
    {
        [$catalog, $db] = $this->storage();
        $id = 'pm_' . str_repeat('f', 24);
        try {
            $catalog->vote($id, $this->profile(), 'pour', 'Texte libre interdit');
            self::fail('Unstructured opinion unexpectedly accepted.');
        } catch (DomainException $error) {
            self::assertSame(400, $error->getCode());
        }
        self::assertSame(
            0, (int) $db->query('SELECT COUNT(*) FROM policy_votes')->fetchColumn(),
        );
    }

    public function testExpiredProfileCannotVote(): void
    {
        [$catalog] = $this->storage();
        $id = 'pm_' . str_repeat('f', 24);
        $catalog->vote($id, $this->profile('a', '66134'), 'pour');
        $expired = $this->profile('b', '66134');
        $expired['expiresAt'] = time() - 1;
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);
        $catalog->vote($id, $expired, 'contre');
    }

    public function testOldProgrammeIsHiddenAndCannotReceiveVotes(): void
    {
        [$catalog, $db] = $this->storage();
        $id = 'pm_' . str_repeat('f', 24);
        $db->exec("UPDATE policy_measures SET source_type = 'programme' WHERE id = '" . $id . "'");
        self::assertSame(0, $catalog->page()['total']);
        self::assertNull($catalog->measure($id));
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(409);
        $catalog->vote($id, $this->profile(), 'pour');
    }

    public function testExplorationFiltersAndVotingDashboardAreConsistent(): void
    {
        [$catalog, $db] = $this->storage();
        $id = 'pm_' . str_repeat('f', 24);
        $closedId = 'pm_' . str_repeat('e', 24);
        $insert = $db->prepare(
            'INSERT INTO policy_measures (
                id, canonical_key, label, category, territory_code, status,
                closes_at, source_type, source_url, source_date, source_reference,
                origin_actor, legal_status, review_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insert->execute([
            $closedId, str_repeat('e', 64), 'Améliorer l’accès aux soins en zone rurale',
            'Santé', 'FR', 'closed', '2026-01-01T00:00:00+00:00',
            'proposition_de_loi', 'https://www.senat.fr/dossier-legislatif/ppl26-999.html',
            '2026-10-20', 'Dépôt 999', 'Auteur confidentiel avant clôture',
            'En première lecture', 'titre original',
        ]);

        $profile = $this->profile();
        self::assertEqualsCanonicalizing(['Éducation', 'Santé'], $catalog->categories());
        self::assertSame(2, $catalog->summary(null)['total']);
        self::assertSame(1, $catalog->summary(null)['open']);
        self::assertSame(1, $catalog->summary(null)['pending']);
        self::assertCount(2, $catalog->page()['rows']);
        self::assertSame(1, $catalog->page(1, '', '', 'Santé')['total']);
        self::assertSame(1, $catalog->page(1, '', '', '', 'open')['total']);

        $catalog->vote($id, $profile, 'pour');
        $summary = $catalog->summary($profile);
        self::assertSame(1, $summary['voted']);
        self::assertSame(0, $summary['pending']);
        $voted = $catalog->page(1, '', '', '', 'open', $profile['citizenId'], 'voted');
        self::assertSame(1, $voted['total']);
        self::assertSame('pour', $voted['rows'][0]['myChoice']);
        self::assertSame(
            'Origine cachée tant que le vote est ouvert',
            $voted['rows'][0]['originActor'],
        );
        self::assertSame(
            0, $catalog->page(1, '', '', '', 'open', $profile['citizenId'], 'pending')['total'],
        );
        $closed = $catalog->page(1, '', '', '', 'closed', $profile['citizenId']);
        self::assertSame(1, $closed['total']);
        self::assertSame($closedId, $closed['rows'][0]['id']);
        self::assertSame(
            'Auteur confidentiel avant clôture', $closed['rows'][0]['originActor'],
        );
        self::assertSame(0, $catalog->page(1, '', '', 'Santé\' OR 1=1 --')['total']);
    }

    public function testMissingCatalogueDoesNotCreateDatabase(): void
    {
        $root = sys_get_temp_dir() . '/populorum-absent-' . bin2hex(random_bytes(8));
        $catalog = new PolicyCatalog($root);
        self::assertFalse($catalog->page()['available']);
        self::assertNull($catalog->measure('pm_' . str_repeat('f', 24)));
        self::assertFalse(is_file($root . '/var/policy-catalog.sqlite'));
    }
}
