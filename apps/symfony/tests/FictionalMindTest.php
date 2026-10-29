<?php

declare(strict_types=1);

use App\Service\FictionalMind;
use App\Service\MentalSimulation;
use App\Service\SimulationEngine;
use PHPUnit\Framework\TestCase;

final class FictionalMindTest extends TestCase
{
    private function state(): array
    {
        return json_decode(
            gzdecode(
                file_get_contents(dirname(__DIR__) . '/data/scenario.json.gz'),
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function testEveryLocalDecisionHasTwelveFrozenAnswersAndAnArchivedText(): void
    {
        $state = $this->state();
        $simulation = new MentalSimulation();
        $archive = gzopen(
            dirname(__DIR__) . '/data/citizen-deliberations.ndjson.gz',
            'rb',
        );
        $count = 0;
        foreach ($state['votes'] as $vote) {
            $dossier = $simulation->dossier($state, $vote);
            self::assertSame(
                $dossier,
                json_decode(gzgets($archive), true, 512, JSON_THROW_ON_ERROR),
            );
            self::assertSame(
                SimulationEngine::CATEGORIES,
                array_column($dossier['reasoning'], 'category'),
            );
            self::assertSame($vote['choice'], $dossier['choice']);
            self::assertTrue($dossier['simulated']);
            foreach ($dossier['reasoning'] as $response) {
                self::assertGreaterThan(30, mb_strlen($response['answer']));
            }
            ++$count;
        }
        self::assertFalse(gzgets($archive));
        gzclose($archive);
        self::assertSame(2700, $count);
        $perCitizen = array_count_values(
            array_column($state['votes'], 'citizenId'),
        );
        self::assertSame(
            [9],
            array_values(array_unique(array_values($perCitizen))),
        );
        self::assertCount(
            12,
            array_unique(
                array_column(array_column($state['citizens'], 'mind'), 'voice'),
            ),
        );
        self::assertCount(
            3,
            array_unique(array_column($state['votes'], 'choice')),
        );
    }

    public function testDifferentLearningRatesProduceDifferentResponsesToTheSameError(): void
    {
        $model = new FictionalMind();
        $slow = $model->create('control');
        $slow['learningRate'] = 0.03;
        $slow['openness'] = 0.8;
        $slow['attachment'] = 0.2;
        $slow['beliefs'] = ['p1' => 0.7];
        $fast = $slow;
        $fast['learningRate'] = 0.95;
        $feedback = ['p1' => ['signal' => -0.9, 'reliability' => 0.9]];
        $newSlow = $model->evolve($slow, $feedback, 1);
        $newFast = $model->evolve($fast, $feedback, 1);
        self::assertGreaterThan(
            $newFast['beliefs']['p1'],
            $newSlow['beliefs']['p1'],
        );
        self::assertGreaterThan(0.6, $newSlow['beliefs']['p1']);
        self::assertLessThan(0, $newFast['beliefs']['p1']);
        self::assertSame($slow['voice'], $newSlow['voice']);
        self::assertSame($slow['priorities'], $newSlow['priorities']);
    }

    public function testHistoricalRepliesRemainExactAndTamperingIsRejected(): void
    {
        $state = $this->state();
        $simulation = new MentalSimulation();
        $vote = $state['votes'][0];
        $first = $simulation->dossier($state, $vote, 0);
        self::assertSame(1, $first['profileRevision']);
        self::assertSame(0, $first['round']);
        self::assertSame(2, $simulation->dossier($state, $vote)['round']);
        self::assertGreaterThan(
            0,
            count(
                array_filter(
                    $state['votes'],
                    static fn (array $item): bool => $item['history'][0][
                        'choice'
                    ] !== $item['choice'],
                ),
            ),
        );
        $vote['deliberation']['digest'] = str_repeat('0', 64);
        $this->expectException(DomainException::class);
        $simulation->dossier($state, $vote);
    }

    public function testAdvancementPreservesClosedVotesManualVotesAndSignatures(): void
    {
        $state = $this->state();
        $engine = new SimulationEngine();
        $state = $engine->initializeFixture($state);
        $original = $state['votes'][0];
        $manualChoice = $original['choice'] === 'pour' ? 'contre' : 'pour';
        $state = $engine->apply(
            $state,
            'vote.cast',
            [
                'citizenId' => $original['citizenId'],
                'consultationId' => $original['consultationId'],
                'choice' => $manualChoice,
            ],
            bin2hex(random_bytes(16)),
        );
        self::assertArrayNotHasKey('deliberation', $state['votes'][0]);
        self::assertArrayHasKey(
            'deliberation',
            end($state['votes'][0]['history']),
        );
        $closed = array_values(
            array_filter(
                $state['votes'],
                static fn (array $vote): bool => $vote['consultationId'] ===
                    'c4',
            ),
        );
        $first = new MentalSimulation()->dossier($state, $original, 0);
        $state = $engine->apply(
            $state,
            'simulation.advance',
            ['citizenId' => 'u10'],
            bin2hex(random_bytes(16)),
        );
        self::assertSame(3, $state['mentalSimulation']['round']);
        self::assertSame($manualChoice, $state['votes'][0]['choice']);
        self::assertSame(
            $closed,
            array_values(
                array_filter(
                    $state['votes'],
                    static fn (array $vote): bool => $vote['consultationId'] ===
                        'c4',
                ),
            ),
        );
        self::assertSame(
            $first,
            new MentalSimulation()->dossier($state, $original, 0),
        );
        self::assertTrue(
            $engine->verify($engine->publicState($state))['valid'],
        );
        $this->expectException(DomainException::class);
        $engine->apply(
            $state,
            'simulation.advance',
            ['citizenId' => 'u1'],
            bin2hex(random_bytes(16)),
        );
    }

    public function testRoundLimitStopsTheSimulation(): void
    {
        $state = $this->state();
        $state['mentalSimulation']['round'] = MentalSimulation::MAX_ROUND;
        $this->expectException(DomainException::class);
        new MentalSimulation()->advance($state);
    }
}
