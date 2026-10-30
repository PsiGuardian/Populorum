<?php

declare(strict_types=1);

use App\Service\PublicProcurement;
use App\Service\SimulationEngine;
use PHPUnit\Framework\TestCase;

final class PublicProcurementTest extends TestCase
{
    private array $state;
    private array $owner = ['id' => 'u1', 'territoryId' => 't1'];
    private array $bidder = ['id' => 'u2', 'territoryId' => 't2'];
    private array $reviewer = ['id' => 'u3', 'territoryId' => 't1'];
    private int $now;
    private PublicProcurement $service;

    protected function setUp(): void
    {
        $this->now = strtotime('2026-10-30T12:00:00Z');
        $this->service = new PublicProcurement($this->now);
        $this->state = new SimulationEngine()->initializeFixture([
            'simulation' => true,
            'schemaVersion' => 1,
            'consultations' => [],
            'events' => [],
            'operations' => [],
            'citizens' => [$this->owner, $this->bidder, $this->reviewer],
            'proposals' => [],
            'missions' => [],
            'workers' => [
                [
                    'id' => 'u4',
                    'citizenId' => 'u4',
                    'territoryId' => 't1',
                    'available' => true,
                    'optIn' => true,
                    'skills' => ['maintenance'],
                    'capacityHours' => 8,
                ],
            ],
        ]);
    }

    private function create(array $extra = []): string
    {
        return $this->service->apply(
            $this->state,
            'tender.create',
            array_replace(
                [
                    'title' => 'Réfection fictive de mobilier',
                    'scope' => 'Quatre bancs, même peinture et contrôle inclus.',
                    'location' => 'Place fictive du laboratoire',
                    'service' => 'maintenance',
                    'risks' => 'Aucun travail en hauteur ou sur réseau.',
                    'cause' => 'Usure supposée à vérifier.',
                    'expectedEffect' => 'Quatre bancs utilisables.',
                    'indicator' => 'Nombre de bancs utilisables après contrôle.',
                    'uncertainty' => 'État intérieur inconnu.',
                    'deadline' => '2026-10-31 13:00',
                    'budgetCents' => 3000000,
                    'referenceCents' => 300000,
                    'referenceBasis' => 'Référence fictive comparable, à vérifier.',
                    'horizonYears' => 10,
                ],
                $extra,
            ),
            $this->owner,
        );
    }

    private function act(
        string $action,
        string $id,
        array $actor,
        array $extra = [],
    ): string {
        return $this->service->apply(
            $this->state,
            $action,
            [
                'tenderId' => $id,
                'reason' => 'Étape fictive motivée et vérifiable.',
            ] + $extra,
            $actor,
        );
    }

    private function bid(
        string $id,
        array $extra = [],
        ?array $actor = null,
    ): void {
        $this->act(
            'tender.bid',
            $id,
            $actor ?? $this->bidder,
            array_replace(
                [
                    'company' => 'Entreprise Fictive du Banc',
                    'lines' => "Peinture | 4 | 50000\nMain-d’œuvre | 10 | 10000",
                    'qualifications' => 'Compétences uniquement simulées.',
                    'exclusions' => 'Aucune réserve.',
                    'days' => 30,
                    'warrantyMonths' => 24,
                    'annualMaintenanceCents' => 1000,
                    'fictionalConsent' => true,
                ],
                $extra,
            ),
        );
    }

    private function open(string $id): void
    {
        $this->service = new PublicProcurement($this->now + 86400);
        $this->act('tender.open', $id, $this->owner);
    }

    private function award(string $id): string
    {
        $this->bid($id);
        $this->open($id);
        $offer = $this->state['tenders'][0]['offers'][0]['id'];
        $this->act('tender.evaluate', $id, $this->reviewer, [
            'offerId' => $offer,
            'qualityScore' => 80,
            'compliant' => true,
        ]);
        $this->act('tender.award', $id, $this->owner, ['selection' => $offer]);

        return $offer;
    }

    private function refused(callable $call, int $code): void
    {
        try {
            $call();
            self::fail('Action should have been rejected');
        } catch (DomainException $exception) {
            self::assertSame(
                $code,
                $exception->getCode(),
                $exception->getMessage(),
            );
        }
    }

    public function testLineItemsAreIntegerBoundedAndNeverEvaluatedAsCode(): void
    {
        self::assertSame(
            12500,
            $this->service->lineItems('Heures | 5 | 2500')['totalCents'],
        );
        foreach (
            [
                'Poste | -2 | 400',
                'Poste | 2.5 | 400',
                'Poste | 1 | 3e5',
                'Poste | 100000 | 100000000',
                'Poste | 1 | 0',
                'Poste | 1 | 400 | extra',
                "Poste | 1 | 400\n",
            ] as $i => $input
        ) {
            if ($i === 6) {
                self::assertSame(
                    400,
                    $this->service->lineItems($input)['totalCents'],
                );
            } else {
                $this->refused(fn () => $this->service->lineItems($input), 400);
            }
        }
        self::assertSame(
            'SQL SELECT literal',
            $this->service->lineItems('SQL SELECT literal | 1 | 1')['items'][0][
                'label'
            ],
        );
    }

    public function testPublicExportsContainNoPlaintextOffersOrBidAuditPayloadBeforeOpening(): void
    {
        $fixture = json_decode(
            gzdecode(
                file_get_contents(dirname(__DIR__) . '/data/scenario.json.gz'),
            ),
            true,
        );
        $engine = new SimulationEngine();
        $fixture = $engine->apply(
            $fixture,
            'tender.create',
            [
                'citizenId' => 'u1',
                'title' => 'Besoin fictif test',
                'scope' => 'Périmètre identique.',
                'location' => 'Place fictive',
                'service' => 'maintenance',
                'risks' => 'Risque faible.',
                'cause' => 'Usure supposée.',
                'expectedEffect' => 'Bon état.',
                'indicator' => 'État constaté.',
                'uncertainty' => 'Cause incertaine.',
                'deadline' => new DateTimeImmutable(
                    '+2 days',
                    new DateTimeZone('Europe/Paris'),
                )->format('Y-m-d H:i'),
                'budgetCents' => 3000000,
                'referenceCents' => 300000,
                'referenceBasis' => 'Source fictive.',
                'horizonYears' => 10,
            ],
            bin2hex(random_bytes(16)),
        );
        $id = $fixture['tenders'][0]['id'];
        $votes = $fixture['votes'];
        $fixture = $engine->apply(
            $fixture,
            'tender.bid',
            [
                'citizenId' => 'u2',
                'tenderId' => $id,
                'reason' => 'PRIVATE_REASON_74391',
                'company' => 'PRIVATE_COMPANY_91247',
                'lines' => 'PRIVATE_LINE_51941 | 1 | 314159',
                'qualifications' => 'PRIVATE_SKILL_54673',
                'exclusions' => 'PRIVATE_RESERVE_27819',
                'days' => 20,
                'warrantyMonths' => 12,
                'annualMaintenanceCents' => 0,
                'fictionalConsent' => true,
            ],
            bin2hex(random_bytes(16)),
        );
        $public = $engine->publicState($fixture);
        $json = json_encode($public);
        foreach (
            [
                'PRIVATE_REASON_74391',
                'PRIVATE_COMPANY_91247',
                'PRIVATE_LINE_51941',
                'PRIVATE_SKILL_54673',
                'PRIVATE_RESERVE_27819',
                '314159',
            ] as $secret
        ) {
            self::assertStringNotContainsString($secret, $json);
        }
        self::assertArrayNotHasKey('keyPair', $public);
        self::assertSame($votes, $fixture['votes']);
        self::assertTrue($engine->verify($fixture)['valid']);
        self::assertTrue($engine->verify($public)['valid']);
    }

    public function testDeadlineAuthorityAndLatestNonWithdrawnVersion(): void
    {
        $id = $this->create();
        $this->bid($id, ['company' => 'Version ancienne']);
        $this->bid($id, ['company' => 'Version courante']);
        $this->refused(
            fn () => $this->act('tender.open', $id, $this->owner),
            409,
        );
        $this->refused(
            fn () => $this->act('tender.open', $id, $this->reviewer),
            403,
        );
        $this->open($id);
        self::assertCount(1, $this->state['tenders'][0]['offers']);
        self::assertSame(
            'Version courante',
            $this->state['tenders'][0]['offers'][0]['company'],
        );
        self::assertCount(2, $this->state['tenders'][0]['sealedOffers']);
        $this->refused(fn () => $this->bid($id), 409);
    }

    public function testWithdrawalBeforeOpeningAndEnvelopeTamperDetection(): void
    {
        $id = $this->create();
        $this->bid($id);
        $this->act('tender.withdraw', $id, $this->bidder);
        $this->open($id);
        self::assertSame([], $this->state['tenders'][0]['offers']);
        $this->setUp();
        $id = $this->create();
        $this->bid($id);
        $this->state['tenders'][0]['sealedOffers'][0]['envelope'][50] = '!';
        $this->refused(fn () => $this->open($id), 409);
    }

    public function testQuestionsAnswersAndCriteriaRemainPublishedAndFrozen(): void
    {
        $id = $this->create();
        $this->act('tender.question', $id, $this->bidder, [
            'question' => 'Quelle surface exacte ?',
        ]);
        $qid = $this->state['tenders'][0]['questions'][0]['id'];
        $this->act('tender.answer', $id, $this->owner, [
            'questionId' => $qid,
            'answer' => 'Quatre bancs de deux mètres, périmètre inchangé.',
        ]);
        self::assertSame(
            PublicProcurement::WEIGHTS,
            $this->state['tenders'][0]['weights'],
        );
        $this->refused(
            fn () => $this->act('tender.answer', $id, $this->owner, [
                'questionId' => $qid,
                'answer' => 'Réécriture refusée.',
            ]),
            409,
        );
    }

    public function testIndependentEvaluationAndPriceAlertRequireJustification(): void
    {
        $id = $this->create();
        $this->bid($id, ['lines' => 'Même prestation | 1 | 3000000']);
        $this->open($id);
        $oid = $this->state['tenders'][0]['offers'][0]['id'];
        $evaluation = [
            'offerId' => $oid,
            'qualityScore' => 90,
            'compliant' => true,
        ];
        $this->refused(
            fn () => $this->act(
                'tender.evaluate',
                $id,
                $this->owner,
                $evaluation,
            ),
            403,
        );
        $this->refused(
            fn () => $this->act(
                'tender.evaluate',
                $id,
                $this->bidder,
                $evaluation,
            ),
            403,
        );
        $this->refused(
            fn () => $this->act('tender.award', $id, $this->owner, [
                'selection' => $oid,
            ]),
            409,
        );
        $this->act('tender.evaluate', $id, $this->reviewer, $evaluation);
        self::assertTrue(
            $this->service->ranking($this->state['tenders'][0])[0][
                'priceAlert'
            ],
        );
        $this->refused(
            fn () => $this->act('tender.award', $id, $this->owner, [
                'selection' => $oid,
            ]),
            409,
        );
        $this->act('tender.award', $id, $this->owner, [
            'selection' => $oid,
            'priceJustification' => 'Référence non comparable : démontage spécifique inclus, hypothèse à contrôler.',
        ]);
        self::assertSame(
            3000000,
            $this->state['tenders'][0]['authorizedCents'],
        );
    }

    public function testLifecycleCostsCanMakeCheaperInitialOfferLessAttractive(): void
    {
        $id = $this->create();
        $this->bid($id, [
            'company' => 'Moins cher au départ',
            'lines' => 'Prestation | 1 | 100000',
            'annualMaintenanceCents' => 100000,
        ]);
        $this->bid(
            $id,
            [
                'company' => 'Durable',
                'lines' => 'Prestation | 1 | 300000',
                'annualMaintenanceCents' => 0,
            ],
            ['id' => 'u5', 'territoryId' => 't3'],
        );
        $this->open($id);
        foreach ($this->state['tenders'][0]['offers'] as $offer) {
            $this->act('tender.evaluate', $id, $this->reviewer, [
                'offerId' => $offer['id'],
                'qualityScore' => 80,
                'compliant' => true,
            ]);
        }
        $ranked = $this->service->ranking($this->state['tenders'][0]);
        self::assertSame('Durable', $ranked[0]['company']);
        self::assertSame(300000, $ranked[0]['lifecycleCents']);
        self::assertSame(1100000, $ranked[1]['lifecycleCents']);
    }

    public function testAlertsMustReceiveIndependentResponseBeforeAward(): void
    {
        $id = $this->create();
        $this->bid($id);
        $this->open($id);
        $oid = $this->state['tenders'][0]['offers'][0]['id'];
        $this->act('tender.evaluate', $id, $this->reviewer, [
            'offerId' => $oid,
            'qualityScore' => 70,
            'compliant' => true,
        ]);
        $this->act('tender.alert', $id, $this->owner, [
            'evidence' => 'Comparaison avec une intervention fictive de même périmètre.',
        ]);
        $aid = $this->state['tenders'][0]['alerts'][0]['id'];
        $this->refused(
            fn () => $this->act('tender.award', $id, $this->owner, [
                'selection' => $oid,
            ]),
            409,
        );
        $this->act('tender.explain', $id, $this->reviewer, [
            'alertId' => $aid,
            'response' => 'Les quantités concordent ; prix à suivre à la réception.',
        ]);
        $this->act('tender.award', $id, $this->owner, ['selection' => $oid]);
        self::assertSame('awarded', $this->state['tenders'][0]['status']);
    }

    public function testAmendmentsDeliveryInspectionAndPaymentCannotSkipControls(): void
    {
        $id = $this->create();
        $this->award($id);
        $this->refused(
            fn () => $this->act('tender.pay', $id, $this->owner, [
                'paymentReference' => 'PAY-001',
            ]),
            409,
        );
        $this->act('tender.amend', $id, $this->owner, [
            'deltaCents' => 10000,
            'scope' => 'Préparation supplémentaire documentée.',
        ]);
        $aid = $this->state['tenders'][0]['amendments'][0]['id'];
        self::assertSame(300000, $this->state['tenders'][0]['authorizedCents']);
        $this->refused(
            fn () => $this->act('tender.approve', $id, $this->bidder, [
                'amendmentId' => $aid,
            ]),
            403,
        );
        $this->act('tender.approve', $id, $this->reviewer, [
            'amendmentId' => $aid,
        ]);
        self::assertSame(310000, $this->state['tenders'][0]['authorizedCents']);
        self::assertSame(
            300000,
            $this->state['tenders'][0]['selection']['amountCents'],
        );
        $this->act('tender.delivery', $id, $this->bidder);
        $this->refused(
            fn () => $this->act('tender.delivery', $id, $this->bidder, [
                'actualCents' => 310001,
                'invoice' => 'INV-001',
                'observedEffect' => 'Quatre bancs conformes.',
            ]),
            400,
        );
        $this->act('tender.delivery', $id, $this->bidder, [
            'actualCents' => 310000,
            'invoice' => 'INV-001',
            'observedEffect' => 'Quatre bancs conformes.',
        ]);
        $this->refused(
            fn () => $this->act('tender.inspect', $id, $this->owner, [
                'accepted' => true,
            ]),
            403,
        );
        $this->act('tender.inspect', $id, $this->reviewer, [
            'accepted' => false,
        ]);
        self::assertSame('in_progress', $this->state['tenders'][0]['status']);
        $this->act('tender.delivery', $id, $this->bidder, [
            'actualCents' => 305000,
            'invoice' => 'INV-002',
            'observedEffect' => 'Correction contrôlable, quatre bancs.',
        ]);
        $this->act('tender.inspect', $id, $this->reviewer, [
            'accepted' => true,
        ]);
        $this->act('tender.pay', $id, $this->owner, [
            'paymentReference' => 'PAY-FICTIF-002',
        ]);
        self::assertCount(2, $this->state['tenders'][0]['invoices']);
        self::assertSame(
            305000,
            $this->state['tenders'][0]['payment']['amountCents'],
        );
        $this->refused(
            fn () => $this->act('tender.pay', $id, $this->owner, [
                'paymentReference' => 'PAY-REPLAY',
            ]),
            409,
        );
    }

    private function plan(string $id): void
    {
        $this->act('tender.participation', $id, $this->owner, [
            'scope' => 'Même résultat, tâches simples uniquement.',
            'safety' => 'Matériel, assurance et encadrant prévus.',
            'fallback' => 'Entreprise fictive de repli.',
            'hoursNeeded' => 4,
            'hourValueCents' => 2000,
            'materialsCents' => 50000,
            'insuranceCents' => 10000,
            'supervisionCents' => 20000,
            'controlCents' => 10000,
            'lowRisk' => true,
        ]);
    }

    public function testParticipationAccountsForCostsAndVoluntaryWithdrawalPausesWork(): void
    {
        $id = $this->create();
        $this->plan($id);
        $volunteer = ['id' => 'u4', 'territoryId' => 't1'];
        $this->act('tender.volunteer', $id, $volunteer, [
            'hours' => 4,
            'consent' => true,
        ]);
        $this->open($id);
        $this->act('tender.validate', $id, $this->reviewer, [
            'checked' => true,
        ]);
        $this->act('tender.award', $id, $this->owner, [
            'selection' => 'participation',
        ]);
        self::assertSame(90000, $this->state['tenders'][0]['authorizedCents']);
        $this->act('tender.delivery', $id, $this->owner);
        $this->act('tender.volunteer', $id, $volunteer, ['consent' => false]);
        self::assertSame('paused', $this->state['tenders'][0]['status']);
        $this->refused(
            fn () => $this->act('tender.delivery', $id, $this->owner),
            409,
        );
        $this->act('tender.cancel', $id, $this->owner, [
            'actualCents' => 10000,
        ]);
        self::assertSame(10000, $this->state['tenders'][0]['actualCents']);
    }

    public function testParticipationCompletionKeepsTimeValueSeparateFromPublicExpense(): void
    {
        $id = $this->create();
        $this->plan($id);
        $volunteer = ['id' => 'u4', 'territoryId' => 't1'];
        $this->act('tender.volunteer', $id, $volunteer, ['hours' => 4, 'consent' => true]);
        $this->open($id);
        $this->act('tender.validate', $id, $this->reviewer, ['checked' => true]);
        $this->act('tender.award', $id, $this->owner, ['selection' => 'participation']);
        self::assertTrue(PublicProcurement::busy($this->state, 'u4'));
        $this->act('tender.delivery', $id, $this->owner);
        $this->act('tender.delivery', $id, $this->owner, ['actualCents' => 85000, 'invoice' => 'PARTICIPATION-001', 'observedEffect' => 'Quatre bancs utilisables, résultat simulé.', 'volunteerHours' => 3]);
        $this->act('tender.inspect', $id, $this->reviewer, ['accepted' => true]);
        $this->act('tender.pay', $id, $this->owner, ['paymentReference' => 'PAY-PART-001']);
        self::assertSame(3, $this->state['tenders'][0]['invoices'][0]['volunteerHours']);
        self::assertSame(85000, $this->state['tenders'][0]['payment']['amountCents']);
        self::assertFalse(PublicProcurement::busy($this->state, 'u4'));
    }

    public function testBusyWorkersCannotBeReservedTwiceAcrossModules(): void
    {
        $id = $this->create();
        $this->plan($id);
        $this->state['missions'][] = ['id' => 'm1', 'workerId' => 'u4', 'status' => 'accepted'];
        $volunteer = ['id' => 'u4', 'territoryId' => 't1'];
        $this->refused(fn () => $this->act('tender.volunteer', $id, $volunteer, ['hours' => 4, 'consent' => true]), 409);
        self::assertTrue(PublicProcurement::busy($this->state, 'u4'));
    }

    public function testRegulatedServicesAndUnverifiedAvailabilityCannotUseParticipation(): void
    {
        $id = $this->create(['service' => 'demolition']);
        $this->refused(fn () => $this->plan($id), 409);
        $this->state['tenders'] = [];
        $id = $this->create();
        $this->plan($id);
        $this->refused(
            fn () => $this->act('tender.volunteer', $id, $this->bidder, [
                'hours' => 4,
                'consent' => true,
            ]),
            409,
        );
        $this->open($id);
        $this->refused(
            fn () => $this->act('tender.award', $id, $this->owner, [
                'selection' => 'participation',
            ]),
            409,
        );
        self::assertSame(
            0,
            count($this->state['tenders'][0]['participation']['volunteers']),
        );
    }

    public function testInvalidDatesLinksAndForeignIdentifiersAreRejected(): void
    {
        $this->refused(
            fn () => $this->create(['deadline' => '2026-02-30 13:00']),
            400,
        );
        $this->refused(fn () => $this->create(['proposalId' => 'unknown']), 400);
        $this->refused(fn () => $this->create(['service' => 'unknown']), 400);
        $this->refused(
            fn () => $this->act('tender.cancel', 'unknown', $this->owner, [
                'actualCents' => 0,
            ]),
            404,
        );
    }
}
