<?php

declare(strict_types=1);

use App\Service\EssentialServices;
use App\Service\ServiceContinuityPlans;
use PHPUnit\Framework\TestCase;

final class ServiceContinuityPlansTest extends TestCase
{
    public function testEveryExistingServiceHasOnePublicContinuityPlan(): void
    {
        $plans = ServiceContinuityPlans::all();
        self::assertCount(count(EssentialServices::CATALOG), $plans);
        self::assertSame(array_keys(EssentialServices::CATALOG), array_keys($plans));
        foreach (EssentialServices::CATALOG as $key => $service) {
            $plan = $plans[$key];
            self::assertSame($key, $plan['id']);
            self::assertSame($service['owner'], $plan['owner']);
            self::assertSame($service['regulated'], $plan['regulated']);
            self::assertNotEmpty($plan['goal'], $key);
            self::assertNotEmpty($plan['prevent'], $key);
            self::assertNotEmpty($plan['alternative'], $key);
            self::assertNotEmpty($plan['restore'], $key);
            self::assertNotEmpty($plan['indicator'], $key);
            self::assertCount(count($service['depends']), $plan['depends']);
        }
    }

    public function testWaterPlanCoversContaminationIntrusionLeaksObstructionAndOutage(): void
    {
        $plan = ServiceContinuityPlans::for('water');
        $scenarios = array_column($plan['scenarios'], null, 'id');
        self::assertEqualsCanonicalizing(
            ['contamination', 'intrusion', 'leak', 'obstruction', 'outage', 'conflict'],
            array_keys($scenarios),
        );
        foreach ($scenarios as $scenario) {
            foreach (['name', 'signal', 'protect', 'continue', 'verify'] as $field) {
                self::assertNotEmpty($scenario[$field]);
            }
        }
        self::assertStringContainsString('ARS', $scenarios['contamination']['protect']);
        self::assertStringContainsString('professionnels habilités', $scenarios['obstruction']['protect']);
        self::assertStringContainsString('sanitaire', $scenarios['leak']['verify']);
        self::assertStringContainsString('séparation des circuits', $scenarios['intrusion']['protect']);
        self::assertGreaterThanOrEqual(4, count($plan['sources']));
        foreach ($plan['sources'] as $source) {
            self::assertStringStartsWith('https://', $source['url']);
        }
    }

    public function testPublicPlanRejectsUnknownServiceAndUsesNoHiddenInfrastructureMetadata(): void
    {
        self::assertCount(4, ServiceContinuityPlans::phases());
        foreach (ServiceContinuityPlans::all() as $plan) {
            self::assertArrayNotHasKey('valves', $plan);
            self::assertArrayNotHasKey('coordinates', $plan);
            self::assertArrayNotHasKey('credentials', $plan);
        }
        $this->expectException(InvalidArgumentException::class);
        ServiceContinuityPlans::for('../../private');
    }
}
