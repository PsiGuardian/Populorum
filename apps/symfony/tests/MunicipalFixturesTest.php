<?php

declare(strict_types=1);

use App\Service\CausalGraph;
use App\Service\MunicipalFixtureBuilder;
use App\Service\SimulationEngine;
use PHPUnit\Framework\TestCase;

final class MunicipalFixturesTest extends TestCase
{
    public function testReproduciblePopulationAndDocumentedProposals(): void
    {
        $state = new MunicipalFixtureBuilder()->build(
            dirname(__DIR__) . '/data',
        );
        self::assertSame(
            hash('sha256', json_encode($state, JSON_THROW_ON_ERROR)),
            hash(
                'sha256',
                json_encode(
                    json_decode(
                        gzdecode(
                            file_get_contents(
                                dirname(__DIR__) . '/data/scenario.json.gz',
                            ),
                        ),
                        true,
                        512,
                        JSON_THROW_ON_ERROR,
                    ),
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );
        self::assertCount(
            300,
            array_unique(array_column($state['citizens'], 'id')),
        );
        foreach ($state['territories'] as $territory) {
            self::assertCount(
                100,
                array_filter(
                    $state['citizens'],
                    static fn (array $citizen): bool => $citizen[
                        'territoryId'
                    ] === $territory['id'],
                ),
            );
            self::assertCount(
                9,
                array_filter(
                    $state['proposals'],
                    static fn (array $proposal): bool => $proposal[
                        'territoryId'
                    ] === $territory['id'],
                ),
            );
        }
        $graph = new CausalGraph();
        foreach ($state['proposals'] as $proposal) {
            self::assertCount(12, $graph->nodes($proposal['nodes']));
            self::assertCount(
                4,
                $graph->edges($proposal['edges'], $proposal['nodes']),
            );
            self::assertNotEmpty($proposal['sources']);
            self::assertNotEmpty($proposal['scope']);
            foreach ($proposal['sources'] as $source) {
                self::assertStringStartsWith('https://', $source['url']);
            }
        }
        $citizens = array_column($state['citizens'], null, 'id');
        foreach ($state['consultations'] as $consultation) {
            self::assertCount(100, $consultation['eligibleIds']);
            foreach ($consultation['eligibleIds'] as $id) {
                self::assertSame(
                    $consultation['territoryId'],
                    $citizens[$id]['territoryId'],
                );
                self::assertTrue($citizens[$id]['consent']);
            }
        }
        $uniqueVotes = [];
        foreach ($state['votes'] as $vote) {
            $key = $vote['consultationId'] . '/' . $vote['citizenId'];
            self::assertArrayNotHasKey($key, $uniqueVotes);
            $uniqueVotes[$key] = true;
        }
        self::assertCount(2700, $uniqueVotes);
        $engine = new SimulationEngine();
        $sealed = $engine->initializeFixture($state);
        self::assertTrue(
            $engine->verify($engine->publicState($sealed))['valid'],
        );
        self::assertArrayNotHasKey('keyPair', $engine->publicState($sealed));
    }
}
