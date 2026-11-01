<?php

declare(strict_types=1);

use App\Service\BudgetExplorer;
use PHPUnit\Framework\TestCase;

final class BudgetExplorerTest extends TestCase
{
    private function cities(): array
    {
        return [[
            'city' => ['code' => '75056', 'name' => 'Paris'],
            'population' => 100,
            'totalCents' => 100000,
            'rows' => [
                ['agregat' => 'Dépenses de fonctionnement', 'montant' => 800.0, 'montant_bp' => 800.0, 'montant_ba' => 0.0, 'montant_flux' => 0.0],
                ['agregat' => "Dépenses d'investissement", 'montant' => 200.0, 'montant_bp' => 200.0, 'montant_ba' => 0.0, 'montant_flux' => 0.0],
                ['agregat' => 'Subventions aux personnes de droit privé', 'montant' => 30.0, 'montant_bp' => 30.0, 'montant_ba' => 0.0, 'montant_flux' => 0.0],
            ],
        ]];
    }

    public function testGlobalViewPartitionsExpenseTotalWithoutAddingGrantsAgain(): void
    {
        $view = (new BudgetExplorer())->build($this->cities(), '75056', 'overview', 'amount_desc', '');
        self::assertCount(2, $view['rows']);
        self::assertSame(100000, array_sum(array_column($view['rows'], 'amountCents')));
        self::assertSame(80.0, $view['rows'][0]['share']);
    }

    public function testGrantsDoNotClaimBeneficiariesAreNecessarilyAssociations(): void
    {
        $view = (new BudgetExplorer())->build($this->cities(), '75056', 'grants', 'amount_desc', '');
        self::assertCount(1, $view['rows']);
        self::assertStringContainsString('≠ associations', $view['note']);
    }

    public function testUndocumentedDomainIsNotReportedAsZero(): void
    {
        $view = (new BudgetExplorer())->build($this->cities(), '75056', 'international', 'amount_desc', '');
        self::assertFalse($view['isDirectlyMeasured']);
        self::assertSame([], $view['rows']);
    }

    public function testSearchAndOrderStayBounded(): void
    {
        $view = (new BudgetExplorer())->build($this->cities(), '75056', 'overview', 'label', 'fonctionnement');
        self::assertCount(1, $view['rows']);
        self::assertSame('Dépenses de fonctionnement', $view['rows'][0]['label']);
        $view = (new BudgetExplorer())->build($this->cities(), '75056', 'unrecognized', 'bad-sort', '');
        self::assertSame('overview', $view['category']);
        self::assertSame('amount_desc', $view['sort']);
    }
}
