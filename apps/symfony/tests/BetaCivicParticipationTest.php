<?php

declare(strict_types=1);

use App\Service\FamilySuffrage;
use App\Service\SimulationEngine;
use PHPUnit\Framework\TestCase;

final class BetaCivicParticipationTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(
            gzdecode(file_get_contents(dirname(__DIR__) . '/data/scenario.json.gz')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function testNewProfilesBecomeDistinctSingleMemberFamiliesWithoutRewritingHistory(): void
    {
        $engine = new SimulationEngine();
        $state = $this->fixture();
        $citizensBefore = count($state['citizens']);
        $historical = $state['consultations'];
        $commune = $state['territories'][0] + ['source' => 'https://www.insee.fr/'];
        $firstId = 'beta_' . str_repeat('a', 24);
        $secondId = 'beta_' . str_repeat('b', 24);
        $first = [
            'citizenId' => $firstId, 'firstName' => 'Camille', 'lastName' => 'Test',
            'communeCode' => '75056', 'expiresAt' => time() + 86400,
        ];
        $second = [
            'citizenId' => $secondId, 'firstName' => 'Robin', 'lastName' => 'Essai',
            'communeCode' => '75056', 'expiresAt' => time() + 86400,
        ];

        $state = $engine->enrollBetaParticipant($state, $first, $commune);
        self::assertTrue($engine->verify($state)['valid']);
        $state = $engine->enrollBetaParticipant($state, $first, $commune);
        self::assertCount($citizensBefore + 1, $state['citizens']);
        $state = $engine->enrollBetaParticipant($state, $second, $commune);
        self::assertCount($citizensBefore + 2, $state['citizens']);
        self::assertTrue($engine->verify($state)['valid']);
        self::assertSame($historical, $state['consultations']);

        $citizens = array_column($state['citizens'], null, 'id');
        self::assertSame('Camille Test', $citizens[$firstId]['name']);
        self::assertSame('citizen', $citizens[$firstId]['role']);
        self::assertTrue($citizens[$firstId]['consent']);
        self::assertNotSame($citizens[$firstId]['familyId'], $citizens[$secondId]['familyId']);
        $eligible = [$firstId, $secondId];
        $families = (new FamilySuffrage())->freeze($state['citizens'], $eligible, 't1');
        self::assertCount(2, $families);
        self::assertSame([[$firstId], [$secondId]], array_values($families));

        $consultation = [
            'id' => 'c-new-beta', 'status' => 'closed',
            'closesAt' => '2026-11-01T00:00:00Z',
            'eligibleIds' => $eligible, 'eligibleFamilies' => $families,
            'rules' => [
                'votingUnit' => 'family',
                'familyAggregation' => FamilySuffrage::VERSION,
                'quorum' => 0,
            ],
        ];
        $votes = [
            ['id' => 'v1', 'consultationId' => 'c-new-beta', 'citizenId' => $firstId, 'choice' => 'pour'],
            ['id' => 'v2', 'consultationId' => 'c-new-beta', 'citizenId' => $secondId, 'choice' => 'contre'],
        ];
        $tally = $engine->tally($consultation, $votes);
        self::assertSame(1, $tally['pour']);
        self::assertSame(1, $tally['contre']);
        self::assertSame(2, $tally['eligible']);

        $state = $engine->retireBetaParticipant($state, $firstId);
        self::assertTrue($engine->verify($state)['valid']);
        $citizens = array_column($state['citizens'], null, 'id');
        self::assertTrue($citizens[$firstId]['suspended']);
        self::assertSame('Compte de test effacé', $citizens[$firstId]['name']);
        self::assertSame($historical, $state['consultations']);
    }

    public function testRemoteMunicipalityIsAddedWithoutChangingExistingPilots(): void
    {
        $state = $this->fixture();
        $profile = [
            'citizenId' => 'beta_' . str_repeat('c', 24),
            'firstName' => 'Alex', 'lastName' => 'Local',
            'communeCode' => '66134', 'expiresAt' => time() + 86400,
        ];
        $commune = [
            'code' => '66134', 'name' => 'Passa',
            'region' => 'Occitanie', 'department' => 'Pyrénées-Orientales',
            'source' => 'https://www.insee.fr/',
        ];
        $state = (new SimulationEngine())->enrollBetaParticipant($state, $profile, $commune);
        self::assertSame('c66134', end($state['territories'])['id']);
        self::assertSame('c66134', end($state['citizens'])['territoryId']);
        self::assertSame('t1', $state['territories'][0]['id']);
    }
}
