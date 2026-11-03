<?php

declare(strict_types=1);

use App\Service\SenateDossier;
use PHPUnit\Framework\TestCase;

final class SenateDossierTest extends TestCase
{
    private array $roots = [];

    protected function tearDown(): void
    {
        foreach ($this->roots as $root) {
            foreach (['policy-catalog.sqlite', 'policy-catalog.sqlite-wal',
                'policy-catalog.sqlite-shm'] as $file) {
                @unlink($root . '/var/' . $file);
            }
            @rmdir($root . '/var');
            @rmdir($root);
        }
    }

    private function storage(): array
    {
        $root = sys_get_temp_dir() . '/populorum-senate-' . bin2hex(random_bytes(7));
        self::assertTrue(mkdir($root . '/var', 0700, true));
        $this->roots[] = $root;
        $db = new PDO('sqlite:' . $root . '/var/policy-catalog.sqlite');
        $db->exec(
            'CREATE TABLE policy_measures (
                id TEXT PRIMARY KEY, source_type TEXT, source_url TEXT
            )',
        );
        $db->exec(
            "INSERT INTO policy_measures VALUES (
                'pm_ffffffffffffffffffffffff', 'proposition_de_loi',
                'https://www.senat.fr/dossier-legislatif/ppl22-262.html'
            )",
        );

        return [$root, $db];
    }

    public function testMissingDocumentCacheDoesNotCauseAnHttp500OrInventMotives(): void
    {
        [$root] = $this->storage();
        $service = new SenateDossier($root);
        $entry = $service->forMeasure('pm_' . str_repeat('f', 24));
        self::assertSame([], $entry['documents']);
        self::assertSame([], $entry['excerpts']);
        self::assertNull($entry['updatedAt']);
    }

    public function testCachedOriginalMotivesAndReportsAreAvailableBeforeVoting(): void
    {
        [$root, $db] = $this->storage();
        $db->exec(
            'CREATE TABLE policy_senate_documents (
                measure_id TEXT, kind TEXT, label TEXT, url TEXT,
                source_section TEXT, sort_order INTEGER
            );
            CREATE TABLE policy_senate_context (
                measure_id TEXT PRIMARY KEY, excerpt_text TEXT,
                excerpt_url TEXT, synced_at TEXT
            );',
        );
        $id = 'pm_' . str_repeat('f', 24);
        $insert = $db->prepare(
            'INSERT INTO policy_senate_documents VALUES (?, ?, ?, ?, ?, ?)',
        );
        $insert->execute([
            $id, 'rapport', 'Rapport n° 376',
            'https://www.senat.fr/rap/l22-376/l22-376.html',
            'Travaux de commission', 0,
        ]);
        $insert->execute([
            $id, 'expose', 'Exposé des motifs',
            'https://www.senat.fr/leg/exposes-des-motifs/ppl22-262-expose.html',
            'Dépôt au Sénat', 1,
        ]);
        $insert = $db->prepare(
            'INSERT INTO policy_senate_context VALUES (?, ?, ?, ?)',
        );
        $insert->execute([
            $id, 'Les auteurs invoquent la situation rurale.' . "\n\n"
                . 'Ces motifs sont des affirmations attribuées.',
            'https://www.senat.fr/leg/exposes-des-motifs/ppl22-262-expose.html',
            '2026-11-03T21:00:00+00:00',
        ]);
        $entry = (new SenateDossier($root))->forMeasure($id);
        self::assertCount(2, $entry['documents']);
        self::assertCount(1, $entry['groups']['rapport']);
        self::assertCount(1, $entry['groups']['expose']);
        self::assertCount(2, $entry['excerpts']);
        self::assertSame('Les auteurs invoquent la situation rurale.', $entry['excerpts'][0]);
        self::assertNotEmpty($entry['updatedAt']);
        self::assertSame([], (new SenateDossier($root))->forMeasure('pm_' . str_repeat('e', 24))['documents']);
    }
}
