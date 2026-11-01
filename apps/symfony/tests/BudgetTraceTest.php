<?php

declare(strict_types=1);

use App\Service\BudgetTrace;
use PHPUnit\Framework\TestCase;

final class BudgetTraceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/budget_trace_' . bin2hex(random_bytes(8));
        mkdir($this->root . '/data/research', 0700, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/data/research/public-funding-links.json');
        @rmdir($this->root . '/data/research');
        @rmdir($this->root . '/data');
        @rmdir($this->root);
    }

    private function cities(): array
    {
        return [['city' => ['code' => '75056', 'name' => 'Paris'],
            'amounts' => ['Impôts locaux' => 140000], 'totalCents' => 1000000]];
    }

    public function testMissingDatasetDoesNotInventFinalDestination(): void
    {
        $view = (new BudgetTrace($this->root))->build($this->cities(), '75056', 'overview', 'all', '');
        self::assertCount(4, $view['publicStages']);
        self::assertNull($view['publicStages'][3]['amountCents']);
        self::assertSame([], $view['records']);
    }

    public function testLinkedPublicDocumentsProduceAProvenTrail(): void
    {
        $parent = [
            'id' => 'grant2025', 'cityCode' => '75056', 'category' => 'grants',
            'stage' => 'paid', 'destination' => 'unknown', 'amountCents' => 10000,
            'sourceUrl' => 'https://example.org/public-decision', 'documentDate' => '2025-06-10',
            'from' => 'Collectivité test', 'to' => 'Association test', 'parentId' => '',
            'purpose' => 'Projet test fictif, aucun paiement réel',
        ];
        $child = [
            'id' => 'use2025', 'cityCode' => '75056', 'category' => 'grants',
            'stage' => 'spent', 'destination' => 'france', 'amountCents' => 8000,
            'sourceUrl' => 'https://example.org/finance-report', 'documentDate' => '2025-12-20',
            'from' => 'Association test', 'to' => 'Activité test', 'parentId' => 'grant2025',
            'purpose' => 'Démonstration synthétique de l’interface',
        ];
        file_put_contents($this->root . '/data/research/public-funding-links.json',
            json_encode(['schema' => 'populorum-funding-links-v1', 'records' => [$parent, $child]], JSON_THROW_ON_ERROR));
        $view = (new BudgetTrace($this->root))->build($this->cities(), '75056', 'grants', 'france', 'use2025');
        self::assertCount(1, $view['records']);
        self::assertSame(['grant2025', 'use2025'], array_column($view['trail'], 'id'));
    }
}
