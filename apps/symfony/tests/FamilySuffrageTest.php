<?php

declare(strict_types=1);

use App\Service\FamilySuffrage;
use App\Service\SimulationEngine;
use PHPUnit\Framework\TestCase;

final class FamilySuffrageTest extends TestCase
{
    private function consultation(array $families): array
    {
        return [
            'id' => 'c-test',
            'status' => 'closed',
            'closesAt' => '2026-10-01',
            'rules' => [
                'votingUnit' => 'family',
                'familyAggregation' => FamilySuffrage::VERSION,
                'quorum' => 50,
            ],
            'eligibleFamilies' => $families,
            'eligibleIds' => array_merge([], ...array_values($families)),
        ];
    }

    private function votes(array $choices): array
    {
        $votes = [];
        foreach ($choices as $id => $choice) {
            $votes[] = [
                'consultationId' => 'c-test',
                'citizenId' => $id,
                'choice' => $choice,
            ];
        }

        return $votes;
    }

    public function testLargeFamilyCannotOutweighTwoSmallFamilies(): void
    {
        $large = array_map(static fn (int $i): string => 'u' . $i, range(1, 12));
        $consultation = $this->consultation([
            'large' => $large,
            'small-a' => ['u13'],
            'small-b' => ['u14'],
        ]);
        $choices = array_fill_keys($large, 'pour') + [
            'u13' => 'contre',
            'u14' => 'contre',
        ];
        $tally = new SimulationEngine()->tally(
            $consultation,
            $this->votes($choices),
        );
        self::assertSame(12, $tally['individual']['pour']);
        self::assertSame(2, $tally['individual']['contre']);
        self::assertSame(1, $tally['pour']);
        self::assertSame(2, $tally['contre']);
        self::assertSame(3, $tally['total']);
        self::assertFalse($tally['adopted']);
    }

    public function testTieAndOnlyAbstentionsBecomeOneFamilyAbstentionEach(): void
    {
        $consultation = $this->consultation([
            'tie' => ['a', 'b'],
            'abstains' => ['c', 'd'],
            'absent' => ['e', 'f'],
        ]);
        $tally = new SimulationEngine()->tally(
            $consultation,
            $this->votes(['a' => 'pour', 'b' => 'contre', 'c' => 'abstention']),
        );
        self::assertSame(2, $tally['abstention']);
        self::assertSame(0, $tally['expressed']);
        self::assertSame(2, $tally['total']);
        self::assertSame(3, $tally['eligible']);
        self::assertNull($tally['families']['absent']['choice']);
        self::assertTrue($tally['quorumReached']);
        self::assertFalse($tally['adopted']);
    }

    public function testIndividualChangeAltersOnlyItsFamilyDecision(): void
    {
        $consultation = $this->consultation([
            'one' => ['a', 'b', 'c'],
            'two' => ['d'],
        ]);
        $before = [
            'a' => 'pour',
            'b' => 'pour',
            'c' => 'contre',
            'd' => 'contre',
        ];
        $engine = new SimulationEngine();
        $first = $engine->tally($consultation, $this->votes($before));
        $before['b'] = 'contre';
        $second = $engine->tally($consultation, $this->votes($before));
        self::assertSame('pour', $first['families']['one']['choice']);
        self::assertSame('contre', $second['families']['one']['choice']);
        self::assertSame($first['families']['two'], $second['families']['two']);
        self::assertSame(2, $second['total']);
    }

    public function testOnePersonCannotBeAttachedToTwoFrozenFamilies(): void
    {
        $consultation = $this->consultation(['one' => ['a'], 'two' => ['a']]);
        $this->expectException(DomainException::class);
        new FamilySuffrage()->count(
            $consultation,
            $this->votes(['a' => 'pour']),
        );
    }

    public function testUnassignedOpinionIsVisibleButHasNoAdoptionWeight(): void
    {
        $consultation = $this->consultation(['one' => ['a']]);
        $consultation['eligibleIds'][] = 'b';
        $tally = new SimulationEngine()->tally(
            $consultation,
            $this->votes(['b' => 'pour']),
        );
        self::assertSame(1, $tally['individual']['pour']);
        self::assertSame(1, $tally['individual']['unassigned']);
        self::assertSame(0, $tally['pour']);
        self::assertSame(0, $tally['total']);
        self::assertFalse($tally['adopted']);
    }

    public function testMembershipAndRuleAreFrozenWhenANewConsultationOpens(): void
    {
        $state = json_decode(
            gzdecode(
                file_get_contents(dirname(__DIR__) . '/data/scenario.json.gz'),
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $state['consultations'][1]['status'] = 'closed';
        $engine = new SimulationEngine();
        $state = $engine->apply(
            $state,
            'consultation.open',
            [
                'citizenId' => 'u2',
                'proposalId' => 'p2',
                'days' => 3,
                'quorum' => 50,
            ],
            bin2hex(random_bytes(16)),
        );
        $opened = end($state['consultations']);
        self::assertSame('family', $opened['rules']['votingUnit']);
        self::assertSame(
            FamilySuffrage::VERSION,
            $opened['rules']['familyAggregation'],
        );
        self::assertCount(
            100,
            array_merge([], ...array_values($opened['eligibleFamilies'])),
        );
        $frozen = $opened['eligibleFamilies'];
        $state['citizens'][0]['familyId'] = 'new-family';
        self::assertSame($frozen, $opened['eligibleFamilies']);
        self::assertNotSame(
            $frozen,
            new FamilySuffrage()->freeze(
                $state['citizens'],
                $opened['eligibleIds'],
                't1',
            ),
        );
    }

    public function testCreatingAnotherCharacterDoesNotCreateAnotherFamilyVote(): void
    {
        $state = json_decode(
            gzdecode(
                file_get_contents(dirname(__DIR__) . '/data/scenario.json.gz'),
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $before = new FamilySuffrage()->freeze(
            $state['citizens'],
            array_column($state['citizens'], 'id'),
            't1',
        );
        $state = new SimulationEngine()->apply(
            $state,
            'citizen.create',
            [
                'citizenId' => 'u1',
                'name' => 'Nouveau personnage sans rattachement',
                'territoryId' => 't1',
                'familyId' => 'self-declared-extra-vote',
            ],
            bin2hex(random_bytes(16)),
        );
        $created = end($state['citizens']);
        self::assertArrayNotHasKey('familyId', $created);
        self::assertSame(
            $before,
            new FamilySuffrage()->freeze(
                $state['citizens'],
                array_column($state['citizens'], 'id'),
                't1',
            ),
        );
    }

    public function testIndependentRecountMatchesAllMunicipalFamilyResults(): void
    {
        $state = json_decode(
            gzdecode(
                file_get_contents(dirname(__DIR__) . '/data/scenario.json.gz'),
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        foreach ($state['consultations'] as $consultation) {
            $consultation['votes'] = array_values(
                array_filter(
                    $state['votes'],
                    static fn (array $vote): bool => $vote['consultationId'] ===
                        $consultation['id'],
                ),
            );
            $file = tempnam(dirname(__DIR__) . '/var', 'family-recount-');
            file_put_contents(
                $file,
                json_encode($consultation, JSON_THROW_ON_ERROR),
            );
            try {
                $process = proc_open(
                    [
                        PHP_BINARY,
                        '-c',
                        php_ini_loaded_file(),
                        dirname(__DIR__) . '/tools/recount.php',
                        $file,
                    ],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                );
                $output = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $error);
                $independent = json_decode(
                    $output,
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $tally = new SimulationEngine()->tally(
                    $consultation,
                    $state['votes'],
                );
                foreach (
                    [
                        'pour',
                        'contre',
                        'abstention',
                        'total',
                        'eligible',
                        'expressed',
                        'adopted',
                    ] as $field
                ) {
                    self::assertSame(
                        $tally[$field],
                        $independent[$field],
                        $field,
                    );
                }
                self::assertLessThanOrEqual(
                    count($consultation['eligibleFamilies']),
                    $tally['total'],
                );
                self::assertSame(100, $tally['individual']['total']);
            } finally {
                unlink($file);
            }
        }
    }
}
