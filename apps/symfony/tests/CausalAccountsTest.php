<?php

declare(strict_types=1);

use App\Service\CausalAccounts;
use App\Service\FictionalNames;
use App\Service\SearchInput;
use App\Service\SimulationEngine;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CausalAccountsTest extends TestCase
{
    private function payload(): array
    {
        return [
            'title' => 'Pourquoi mon personnage hésite à parler',
            'category' => 'Inquiétude fictive',
            'kind' => 'concern',
            'year' => '2025',
            'nature' => 'self_report',
            'disclosure' => 'partial',
            'cause' => 'Il craint une mauvaise interprétation de son avis.',
            'consequence' => 'Il préfère décrire son inquiétude avant de voter.',
            'uncertainty' => 'Cette réaction peut avoir une autre explication.',
            'unknownVariables' => 'Le contexte complet reste inconnu.',
            'reason' => 'Il souhaite commencer par un seul élément.',
            'fictionalConsent' => true,
        ];
    }

    public function testAppendPreservesOriginalAndSeparatesOccurrenceFromPublication(): void
    {
        $state = ['proposals' => []];
        $service = new CausalAccounts();
        $actor = ['id' => 'u1', 'territoryId' => 't1'];
        $id = $service->apply(
            $state,
            'account.create',
            $this->payload(),
            $actor,
        );
        $original = $state['causalAccounts'][0]['entries'][0];
        $addition = array_replace($this->payload(), [
            'accountId' => $id,
            'year' => '',
            'eventDate' => '2025-01-10',
            'eventTime' => '14:30',
            'disclosure' => 'correction',
            'cause' => 'Il distingue maintenant deux interprétations possibles.',
        ]);
        $service->apply($state, 'account.append', $addition, $actor);
        self::assertSame($original, $state['causalAccounts'][0]['entries'][0]);
        $latest = $state['causalAccounts'][0]['entries'][1];
        self::assertSame('minute_declared', $latest['timePrecision']);
        self::assertSame('2025-01-10', $latest['eventDate']);
        self::assertSame('14:30', $latest['eventTime']);
        self::assertSame('Europe/Paris', $latest['timeZone']);
        self::assertNotSame($original['cause'], $latest['cause']);
        self::assertSame(2, $latest['version']);
    }

    public function testAnotherCitizenCannotRewriteOrAppendTheAccount(): void
    {
        $state = ['proposals' => []];
        $service = new CausalAccounts();
        $id = $service->apply($state, 'account.create', $this->payload(), [
            'id' => 'u1',
            'territoryId' => 't1',
        ]);
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);
        $service->apply(
            $state,
            'account.append',
            ['accountId' => $id] + $this->payload(),
            ['id' => 'u2', 'territoryId' => 't1'],
        );
    }

    public function testUnknownTimeIsAllowedButDocumentedClaimsNeedASource(): void
    {
        $state = ['proposals' => []];
        $service = new CausalAccounts();
        $service->apply(
            $state,
            'account.create',
            array_replace($this->payload(), ['year' => '']),
            ['id' => 'u1', 'territoryId' => 't1'],
        );
        self::assertSame(
            'unknown',
            $state['causalAccounts'][0]['entries'][0]['timePrecision'],
        );
        $this->expectException(DomainException::class);
        $service->apply(
            $state,
            'account.create',
            array_replace($this->payload(), ['nature' => 'documented']),
            ['id' => 'u1', 'territoryId' => 't1'],
        );
    }

    public function testInvalidDatesHoursAndConsentAreRejected(): void
    {
        foreach (
            [
                ['eventDate' => '2025-02-30'],
                ['eventTime' => '12:00'],
                ['eventDate' => '2025-01-10', 'eventTime' => '25:71'],
                ['eventDate' => '2024-01-10'],
                ['fictionalConsent' => false],
                ['uncertainty' => ''],
            ] as $invalid
        ) {
            $state = ['proposals' => []];
            try {
                new CausalAccounts()->apply(
                    $state,
                    'account.create',
                    array_replace($this->payload(), $invalid),
                    ['id' => 'u1', 'territoryId' => 't1'],
                );
                self::fail('Invalid narrative accepted.');
            } catch (DomainException $exception) {
                self::assertSame(400, $exception->getCode());
            }
        }
    }

    public function testSignedPersistencePreservesVotesAndFrozenProposals(): void
    {
        $state = json_decode(
            file_get_contents(
                dirname(__DIR__) . '/data/reference-scenario.json',
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $state['keyPair'] = null;
        $beforeVotes = $state['votes'];
        $beforeConsultations = $state['consultations'];
        $engine = new SimulationEngine();
        $state = $engine->apply(
            $state,
            'account.create',
            ['citizenId' => 'u1', 'proposalId' => 'p1'] + $this->payload(),
            bin2hex(random_bytes(16)),
        );
        self::assertTrue($engine->verify($state)['valid']);
        self::assertSame($beforeVotes, $state['votes']);
        self::assertSame($beforeConsultations, $state['consultations']);
        self::assertSame('p1', $state['causalAccounts'][0]['proposalId']);
    }

    public function testSearchTreatsSqlAndHtmlAsLiteralDataAndBoundsResourceUse(): void
    {
        $input = new SearchInput();
        foreach (
            ["' OR 1=1 --", '<script>alert(1)</script>', '%_()', 'find/été'] as $term
        ) {
            self::assertSame($term, $input->term($term));
        }
        foreach (
            [str_repeat('a', 161), ['array'], "find\0bad", "line\nnext"] as $term
        ) {
            try {
                $input->term($term);
                self::fail('Invalid search accepted.');
            } catch (BadRequestHttpException $exception) {
                self::assertSame(400, $exception->getStatusCode());
            }
        }
        $this->expectException(BadRequestHttpException::class);
        $input->territory('t9', [['id' => 't1']]);
    }

    public function testNamesAreFictionalAndDoNotMergeFamiliesAcrossCities(): void
    {
        $service = new FictionalNames();
        $first = $service->identity(0, 1, 1);
        $second = $service->identity(0, 1, 2);
        $otherCity = $service->identity(1, 1, 1);
        self::assertSame($first['lastName'], $second['lastName']);
        self::assertNotSame($first['firstName'], $second['firstName']);
        self::assertNotSame($first['familyLabel'], $otherCity['familyLabel']);
        self::assertStringContainsString('fictif', $first['name']);
    }
}
