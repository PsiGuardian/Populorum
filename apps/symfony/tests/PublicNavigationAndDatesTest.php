<?php

declare(strict_types=1);

use App\Service\PublicFilterPath;
use App\Service\PublicProcurementData;
use PHPUnit\Framework\TestCase;

final class PublicNavigationAndDatesTest extends TestCase
{
    public function testCleanRouteFiltersOmitEmptyParameters(): void
    {
        self::assertSame(
            '/q/web/kind/market/year/2025',
            PublicFilterPath::encode('marches-publics', [
                'q' => 'web', 'kind' => 'market', 'year' => '2025',
                'buyer' => '', 'city' => '', 'department' => '',
            ]),
        );
        self::assertSame(
            ['q' => 'web', 'kind' => 'market', 'year' => '2025'],
            PublicFilterPath::decode('marches-publics', 'q/web/kind/market/year/2025'),
        );
        self::assertSame('/q/66300/page/2', PublicFilterPath::encode('communes', [
            'q' => '66300', 'page' => 2,
        ]));
        self::assertSame('/communes_q/66300/region/76', PublicFilterPath::encode('convergence', [
            'region' => '76', 'communes_q' => '66300',
        ]));
        self::assertSame('', PublicFilterPath::encode('marches-publics', ['q' => '', 'year' => null]));
    }

    public function testInvalidAndRepeatedPathParametersAreRejected(): void
    {
        foreach (['q/web/q/other', 'year/2025/year/2026', 'random/value', 'q/web/extra',
            'q/%ZZ', 'q/../kind/market', 'buyer/1234', 'page/abc'] as $slug) {
            try {
                PublicFilterPath::decode('marches-publics', $slug);
                self::fail('Invalid route accepted: ' . $slug);
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testSuspiciousNotificationDatesNeverBecomeNormalDates(): void
    {
        foreach (['2029-04-21', '6666-06-26', '5054-03-15',
            '2025-02-30', 'not-a-date', ''] as $value) {
            self::assertFalse(PublicProcurementData::dateQuality($value)['valid'], $value);
            self::assertSame($value, PublicProcurementData::dateQuality($value)['raw']);
        }
        self::assertSame('Année manifestement incohérente', PublicProcurementData::dateQuality('6666-06-26')['reason']);
        self::assertSame('Date de notification future, à vérifier', PublicProcurementData::dateQuality('2029-04-21')['reason']);
        self::assertSame('Date calendaire impossible', PublicProcurementData::dateQuality('2025-02-30')['reason']);
        self::assertSame('/q/web/sort/amount_desc', PublicFilterPath::encode('marches-publics', ['q' => 'web', 'sort' => 'amount_desc']));
        self::assertTrue(PublicProcurementData::dateQuality('2024-02-29')['valid']);
        self::assertTrue(PublicProcurementData::dateQuality('2025-04-21')['valid']);
        self::assertFalse(PublicProcurementData::dateQuality('2025-04-31')['valid']);
    }
}
