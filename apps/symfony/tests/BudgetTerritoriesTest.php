<?php

declare(strict_types=1);

use App\Service\BudgetCatalog;
use App\Service\BudgetExplorer;
use App\Service\BudgetTrace;
use App\Service\BudgetTree;
use App\Service\PublicBudget;
use PHPUnit\Framework\TestCase;

final class BudgetTerritoriesTest extends TestCase
{
    private function catalog(): BudgetCatalog
    {
        $dir = dirname(__DIR__);

        return new BudgetCatalog($dir, new PublicBudget($dir));
    }

    public function testScopeDoesNotLeakOtherCityTotals(): void
    {
        $catalog = $this->catalog();
        $marseille = $catalog->city('13055');
        $paris = $catalog->city('75056');
        self::assertNotNull($marseille);
        self::assertNotNull($paris);
        self::assertNotSame($paris['totalCents'], $marseille['totalCents']);
        $v = (new BudgetExplorer())->build([$marseille], '13055', 'overview', 'amount_desc', '');
        self::assertSame($marseille['totalCents'], array_sum(array_column($v['rows'], 'amountCents')));
        self::assertSame('13055', $v['city']['city']['code']);
    }

    public function testFranceNeverInventedFromThreePilots(): void
    {
        $catalog = $this->catalog();
        if ($catalog->coverage()['kind'] === 'pilot') {
            self::assertFalse($catalog->national()['available']);
            self::assertNull($catalog->national()['totalCents']);
        }
    }

    public function testTreeHasRegionalPathsAndStopsWhereEvidenceEnds(): void
    {
        $dir = dirname(__DIR__);
        $tree = new BudgetTree($this->catalog(), new BudgetTrace($dir));
        $root = $tree->children('root');
        self::assertNotEmpty($root['children']);
        $city = $tree->children('city:13055');
        self::assertCount(4, $city['children']);
        $operating = $tree->children('section:13055:operating');
        self::assertCount(5, $operating['children']);
        $leaf = $tree->children($operating['children'][0]['id']);
        self::assertSame('unknown', $leaf['children'][0]['kind']);
        self::assertNull($leaf['children'][0]['amountCents']);
    }

    public function testUnknownMunicipalityDoesNotFallBackToNational(): void
    {
        self::assertNull($this->catalog()->city('00000'));
    }
}
