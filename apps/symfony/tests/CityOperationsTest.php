<?php

declare(strict_types=1);

use App\Service\CityOperations;
use App\Service\EssentialServices;
use App\Service\PublicBudget;
use PHPUnit\Framework\TestCase;

final class CityOperationsTest extends TestCase
{
    private array $state;
    private CityOperations $operations;
    private array $author = ['id' => 'u1', 'territoryId' => 't1'];

    protected function setUp(): void
    {
        $this->operations = new CityOperations();
        $worker = [
            'id' => 'u2',
            'citizenId' => 'u2',
            'territoryId' => 't1',
            'skills' => ['maintenance'],
            'profession' => 'Réparation fictive',
            'available' => true,
            'optIn' => true,
            'zone' => 'center',
            'hourlyCents' => 5000,
            'capacityHours' => 8,
        ];
        $this->state = [
            'citizens' => [$this->author],
            'workers' => [
                $worker,
                array_replace($worker, [
                    'id' => 'u3',
                    'zone' => 'north',
                    'hourlyCents' => 1000,
                ]),
                array_replace($worker, [
                    'id' => 'u4',
                    'territoryId' => 't2',
                    'hourlyCents' => 1,
                ]),
            ],
            'missions' => [],
        ];
    }

    private function create(string $service = 'maintenance'): string
    {
        return $this->operations->apply(
            $this->state,
            'mission.create',
            [
                'title' => 'Inventaire visuel fictif',
                'description' => 'Observation sans intervention technique.',
                'service' => $service,
                'zone' => 'center',
                'priority' => 2,
                'hours' => 2,
                'materialsCents' => 1000,
                'budgetCents' => 20000,
            ],
            $this->author,
        );
    }

    private function act(
        string $action,
        string $id,
        array $actor,
        array $extra = [],
    ): void {
        $this->operations->apply(
            $this->state,
            $action,
            ['missionId' => $id, 'reason' => 'Étape fictive documentée.'] +
                $extra,
            $actor,
        );
    }

    public function testCompetenceConsentCapacityBudgetAndProximityAreRespected(): void
    {
        $id = $this->create();
        $candidates = $this->operations->candidates(
            $this->state,
            $this->state['missions'][0],
        );
        self::assertSame(['u2', 'u3'], array_column($candidates, 'id'));
        self::assertSame(11000, $candidates[0]['quoteCents']);
        $this->state['workers'][0]['optIn'] = false;
        self::assertSame(
            ['u3'],
            array_column(
                $this->operations->candidates(
                    $this->state,
                    $this->state['missions'][0],
                ),
                'id',
            ),
        );
        $this->state['workers'][1]['capacityHours'] = 1;
        self::assertSame(
            [],
            $this->operations->candidates(
                $this->state,
                $this->state['missions'][0],
            ),
        );
    }

    public function testMissionLifecycleRequiresVoluntaryAcceptanceAndIndependentReview(): void
    {
        $id = $this->create();
        $this->act('mission.offer', $id, $this->author, ['workerId' => 'u2']);
        self::assertSame('offered', $this->state['missions'][0]['status']);
        $worker = ['id' => 'u2', 'territoryId' => 't1'];
        $this->act('mission.respond', $id, $worker, ['accept' => true]);
        $this->act('mission.progress', $id, $worker);
        $this->act('mission.progress', $id, $worker, ['actualCents' => 12000]);
        self::assertSame('completed', $this->state['missions'][0]['status']);
        $this->act('mission.review', $id, $this->author);
        self::assertSame('reviewed', $this->state['missions'][0]['status']);
        self::assertSame(12000, $this->state['missions'][0]['actualCents']);
        self::assertCount(5, $this->state['missions'][0]['history']);
        $this->expectException(DomainException::class);
        $this->act('mission.review', $id, $this->author);
    }

    public function testAnotherCitizenCannotAcceptAndDoubleBookingIsBlocked(): void
    {
        $first = $this->create();
        $second = $this->create();
        $this->act('mission.offer', $first, $this->author, [
            'workerId' => 'u2',
        ]);
        try {
            $this->act(
                'mission.respond',
                $first,
                ['id' => 'u3', 'territoryId' => 't1'],
                ['accept' => true],
            );
            self::fail('Usurpation accepted');
        } catch (DomainException $exception) {
            self::assertSame(403, $exception->getCode());
        }
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(409);
        $this->act('mission.offer', $second, $this->author, [
            'workerId' => 'u2',
        ]);
    }

    public function testRegulatedTasksAreNotDispatchedByCitizenMatching(): void
    {
        $id = $this->create('rescue');
        self::assertSame(
            'authority_required',
            $this->state['missions'][0]['status'],
        );
        self::assertSame(
            [],
            $this->operations->candidates(
                $this->state,
                $this->state['missions'][0],
            ),
        );
        $this->expectException(DomainException::class);
        $this->act('mission.offer', $id, $this->author, ['workerId' => 'u2']);
    }

    public function testWorkerCanDeclineAndWithdrawWithoutBeingForced(): void
    {
        $id = $this->create();
        $this->act('mission.offer', $id, $this->author, ['workerId' => 'u2']);
        $worker = ['id' => 'u2', 'territoryId' => 't1'];
        $this->act('mission.respond', $id, $worker, ['accept' => false]);
        self::assertNull($this->state['missions'][0]['workerId']);
        $this->act('mission.offer', $id, $this->author, ['workerId' => 'u2']);
        $this->act('mission.respond', $id, $worker, ['accept' => true]);
        $this->act('mission.cancel', $id, $worker, ['actualCents' => 1500]);
        self::assertSame('cancelled', $this->state['missions'][0]['status']);
        self::assertSame(1500, $this->state['missions'][0]['actualCents']);
    }

    public function testPublicBudgetNeverTurnsMissingDataOrAnExtremeSavingIntoValidatedFeasibility(): void
    {
        $service = new PublicBudget(dirname(__DIR__));
        $fixture = json_decode(
            gzdecode(
                file_get_contents(dirname(__DIR__) . '/data/scenario.json.gz'),
            ),
            true,
        );
        $cities = $service->cities($fixture);
        self::assertCount(3, $cities);
        foreach ($cities as $city) {
            self::assertGreaterThan(
                $city['targetCents'],
                $city['protectedCents'],
            );
            $scenario = $service->scenario($city, 100, 0);
            self::assertSame(
                $city['protectedCents'],
                $scenario['residualCents'],
            );
            self::assertFalse($scenario['targetWithinModel']);
            self::assertFalse($scenario['validatedFeasible']);
            self::assertFalse(
                $service->scenario($city, 100, $city['totalCents'])[
                    'validatedFeasible'
                ],
            );
        }
        self::assertFalse(
            new PublicBudget('/nonexistent')->scenario(
                ['totalCents' => null, 'population' => null],
                0,
                0,
            )['available'],
        );
    }

    public function testMissionMutationsRemainSignedAndDoNotChangeVotes(): void
    {
        $state = json_decode(gzdecode(file_get_contents(dirname(__DIR__) . '/data/scenario.json.gz')), true);
        $beforeVotes = $state['votes'];
        $engine = new App\Service\SimulationEngine();
        $state = $engine->apply($state, 'mission.create', [
            'citizenId' => 'u1', 'title' => 'Inventaire fictif', 'description' => 'Sans intervention technique.',
            'service' => 'maintenance', 'zone' => 'center', 'priority' => 3,
            'hours' => 2, 'materialsCents' => 1500, 'budgetCents' => 20000,
        ], bin2hex(random_bytes(16)));
        self::assertTrue($engine->verify($state)['valid']);
        self::assertSame($beforeVotes, $state['votes']);
        self::assertCount(1, $state['missions']);
    }

    public function testWorkerMayDeclareSeveralSkillsButNotInventAnAuthorization(): void
    {
        $this->operations->apply($this->state, 'worker.profile', [
            'profession' => 'Coordinateur fictif polyvalent', 'skill' => ['maintenance', 'digital'],
            'zone' => 'center', 'hourlyCents' => 2500, 'capacityHours' => 4,
        ], $this->author);
        $worker = array_column($this->state['workers'], null, 'id')['u1'];
        self::assertSame(['maintenance', 'digital'], $worker['skills']);
        self::assertFalse($worker['optIn']);
        self::assertFalse($worker['available']);
    }

    public function testDetailedBalancesAndSourceCountsAreTraceable(): void
    {
        $service = new PublicBudget(dirname(__DIR__));
        self::assertCount(149, $service->dataset()['rows']);
        $total = 0;
        foreach (['75056', '13055', '76351'] as $code) {
            $ledger = $service->ledger($code, '', 1);
            self::assertCount(50, $ledger['rows']);
            $total += $ledger['total'];
        }
        self::assertSame(4322, $total);
        foreach ($service->ledger('76351', '6', 1)['rows'] as $row) {
            self::assertStringStartsWith('6', $row['compte']);
        }
        $this->expectException(DomainException::class);
        $service->ledger('76351', '6 OR 1=1', 1);
    }

    public function testCatalogDependenciesExistAndAll300WorkersKeepTheirCitizenIdentity(): void
    {
        foreach (EssentialServices::CATALOG as $service) {
            foreach ($service['depends'] as $dependency) {
                self::assertArrayHasKey(
                    $dependency,
                    EssentialServices::CATALOG,
                );
            }
        }
        $state = json_decode(
            gzdecode(
                file_get_contents(dirname(__DIR__) . '/data/scenario.json.gz'),
            ),
            true,
        );
        self::assertCount(300, $state['workers']);
        self::assertSame(
            array_column($state['citizens'], 'id'),
            array_column($state['workers'], 'citizenId'),
        );
    }
}
