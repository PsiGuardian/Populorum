<?php

declare(strict_types=1);

use App\Service\CausalLayout;
use App\Service\PublicEvidence;
use PHPUnit\Framework\TestCase;

final class InterfaceEvidenceTest extends TestCase
{
    public function testOfficialRatesHaveTraceableDifferencesAndCorrectDenominators(): void
    {
        $service = new PublicEvidence(dirname(__DIR__));
        $report = $service->compare('Cambriolages de logement');
        self::assertTrue($report['available']);
        self::assertSame('logements', $report['denominator']);
        self::assertCount(3, $report['cities']);
        self::assertStringStartsWith('https://', $report['source']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $report['sourceSha256']);
        foreach ($report['cities'] as $city) {
            self::assertEqualsWithDelta(
                $city['years'][2025]['rate'] - $city['years'][2024]['rate'],
                $city['change'],
                0.000001,
            );
        }
        self::assertSame('habitants', $service->compare('Usage de stupéfiants')['denominator']);
        self::assertSame('Violences physiques hors cadre familial', $service->compare('<script>')['indicator']);
    }

    public function testAbsentPublicDatasetDoesNotInventMeasurements(): void
    {
        $report = (new PublicEvidence('/nonexistent'))->compare();
        self::assertFalse($report['available']);
        self::assertSame([], $report['cities']);
    }

    public function testCausalLayoutFollowsEdgesEvenWhenInputOrderIsReversed(): void
    {
        $layout = (new CausalLayout())->layout([
            'nodes' => [['id' => 'effect'], ['id' => 'cause']],
            'edges' => [['from' => 'cause', 'to' => 'effect', 'relation' => 'contribue à']],
        ]);
        self::assertLessThan($layout['nodes']['effect']['y'], $layout['nodes']['cause']['y']);
        self::assertCount(1, $layout['edges']);
        self::assertSame([], $layout['cycleIds']);
    }

    public function testCyclesAndIsolatedNodesRemainVisibleWithoutInventedEdges(): void
    {
        $layout = (new CausalLayout())->layout([
            'nodes' => [['id' => 'a'], ['id' => 'b'], ['id' => 'isolated']],
            'edges' => [
                ['from' => 'a', 'to' => 'b'],
                ['from' => 'b', 'to' => 'a'],
            ],
        ]);
        self::assertCount(3, $layout['nodes']);
        self::assertCount(2, $layout['edges']);
        self::assertSame(['a', 'b'], $layout['cycleIds']);
    }
}
