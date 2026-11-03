<?php

declare(strict_types=1);

use App\Service\FamilySuffrage;
use App\Service\SimulationEngine;
use PHPUnit\Framework\TestCase;

final class FictionalCitizenCreationTest extends TestCase
{
    private function reference(): array
    {
        $state = json_decode(
            file_get_contents(dirname(__DIR__) . '/data/reference-scenario.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $state['keyPair'] = null;

        return $state;
    }

    public function testCreatedFictionalCharacterCannotMintNewFamilyAndHasNoAutomaticConsent(): void
    {
        $engine = new SimulationEngine();
        $state = $this->reference();
        $before = $state['consultations'];
        $familiesBefore = (new FamilySuffrage())->freeze(
            $state['citizens'],
            array_column($state['citizens'], 'id'),
            't1',
        );
        $count = count($state['citizens']);

        $result = $engine->apply($state, 'citizen.create', [
            'citizenId' => 'u1',
            'name' => 'Personnage temporaire 9999',
            'territoryId' => 't1',
        ], bin2hex(random_bytes(16)));

        self::assertCount($count + 1, $result['citizens']);
        $created = end($result['citizens']);
        self::assertSame('Personnage temporaire 9999', $created['name']);
        self::assertSame('t1', $created['territoryId']);
        self::assertArrayNotHasKey('familyId', $created);
        self::assertTrue($created['fictional']);
        self::assertFalse($created['consent']);
        self::assertTrue($engine->verify($result)['valid']);
        self::assertSame($before, $result['consultations']);

        $families = (new FamilySuffrage())->freeze(
            $result['citizens'],
            array_column($result['citizens'], 'id'),
            't1',
        );
        self::assertSame($familiesBefore, $families);
    }

    public function testBetaAccountCannotMintAdditionalFictionalFamily(): void
    {
        $engine = new SimulationEngine();
        $state = $this->reference();
        $profile = [
            'citizenId' => 'beta_' . str_repeat('a', 24),
            'firstName' => 'Profil', 'lastName' => 'Bêta',
            'communeCode' => '75056', 'expiresAt' => time() + 86400,
        ];
        $commune = $state['territories'][0] + [
            'code' => '75056', 'source' => 'https://www.insee.fr/',
        ];
        $state = $engine->enrollBetaParticipant($state, $profile, $commune);

        self::assertTrue($engine->verify($state)['valid']);
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);
        $engine->apply($state, 'citizen.create', [
            'citizenId' => $profile['citizenId'],
            'name' => 'Famille supplémentaire impossible',
            'territoryId' => 't1',
        ], bin2hex(random_bytes(16)));
    }
}
