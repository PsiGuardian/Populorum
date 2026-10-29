<?php

declare(strict_types=1);

use App\Service\BackupService;
use App\Service\ConvergenceAnalyzer;
use App\Service\ReplicaProtocol;
use App\Service\SimulationEngine;
use App\Service\VoluntaryAgent;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class DomainCompletionTest extends TestCase
{
    private SimulationEngine $engine;
    private array $state;
    private array $files = [];

    protected function setUp(): void
    {
        $this->engine = new SimulationEngine();
        $this->state = json_decode(
            file_get_contents(
                dirname(__DIR__) . '/data/reference-scenario.json',
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->state['keyPair'] = null;
        foreach ($this->state['citizens'] as &$citizen) {
            $citizen['consent'] = true;
        }
        unset($citizen);
        foreach (
            [
                'u2' => ['conciliator'],
                'u5' => ['conciliator'],
                'u3' => ['auditor'],
                'u6' => ['governor'],
            ] as $id => $roles
        ) {
            foreach ($this->state['citizens'] as &$citizen) {
                if ($citizen['id'] === $id) {
                    $citizen['roles'] = $roles;
                }
            }
            unset($citizen);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function apply(string $action, array $payload): array
    {
        return $this->state = $this->engine->apply(
            $this->state,
            $action,
            $payload,
            bin2hex(random_bytes(16)),
        );
    }

    private function temporary(string $suffix = ''): string
    {
        $file =
            dirname(__DIR__) .
            '/var/contract-' .
            bin2hex(random_bytes(10)) .
            $suffix;
        $this->files[] = $file;

        return $file;
    }

    public function testProtocolUsesTheAdoptedVersionAndRequiresPriorIndicators(): void
    {
        $this->state['experiments'] = [];
        $this->state['votes'] = [
            [
                'id' => 'test-vote',
                'consultationId' => 'c2',
                'citizenId' => 'u4',
                'choice' => 'pour',
                'at' => '2026-10-29T09:00:00Z',
                'history' => [],
            ],
            [
                'id' => 'test-vote2',
                'consultationId' => 'c2',
                'citizenId' => 'u5',
                'choice' => 'pour',
                'at' => '2026-10-29T09:00:00Z',
                'history' => [],
            ],
        ];
        $this->state['consultations'][1]['status'] = 'closed';
        $frozen = $this->state['consultations'][1]['frozen'];
        $this->state['proposals'][1]['version'] = 2;
        $this->state['proposals'][1]['indicator'] =
            'Indicateur postérieur qui ne doit pas remplacer le texte adopté';
        $payload = [
            'citizenId' => 'u4',
            'consultationId' => 'c2',
            'baseline' => 120,
            'objective' => 'Observer le pilote pédagogique',
            'hypothesis' => 'Réduction possible des véhicules',
            'unit' => 'véhicules/heure',
            'start' => '2026-10-29',
            'end' => '2027-01-29',
            'stop' => 'Arrêt si risque de sécurité',
            'method' => 'Comptage fictif à heures fixes',
            'limitations' => 'Saison et météo non contrôlées',
        ];
        $this->apply('experiment.create', $payload);
        $experiment = end($this->state['experiments']);
        self::assertSame($frozen['indicator'], $experiment['indicator']);
        self::assertSame(1, $experiment['proposalVersion']);
        self::assertSame($frozen, $experiment['frozenProposal']);
        self::assertSame([], $experiment['observations']);
        $this->expectException(DomainException::class);
        $this->apply('experiment.create', $payload);
    }

    public function testTerritorialAdaptationHasItsOwnRevisionHistory(): void
    {
        $initialConsultations = count($this->state['consultations']);
        $this->apply('territory.adapt', [
            'citizenId' => 'u4',
            'proposalId' => 'p1',
            'territoryId' => 't2',
            'type' => 'adaptation',
            'characteristic' => 'Topographie fictive distincte',
            'reason' => 'Adapter volontairement au terrain local',
            'source' => 'Étude pédagogique datée',
            'alternative' => 'Autre parcours expérimental',
            'review' => 'Après trois mois de mesures',
        ]);
        $adaptation = end($this->state['adaptations']);
        $payload = [
            'citizenId' => 'u4',
            'adaptationId' => $adaptation['id'],
            'reason' => 'Précision après un relevé fictif',
            'probableConsequences' => 'Déplacement possible de la fréquentation',
            'observedConsequences' => 'Aucune mesure réelle disponible',
            'source' => 'Relevé fictif du 29 octobre 2026',
            'review' => 'Bilan contradictoire dans six mois',
        ];
        $this->apply('territory.revise', $payload);
        $updated = end($this->state['adaptations']);
        self::assertSame(2, $updated['version']);
        self::assertSame(1, $updated['revisions'][0]['version']);
        self::assertSame(
            $adaptation['source'],
            $updated['revisions'][0]['snapshot']['source'],
        );
        self::assertCount($initialConsultations, $this->state['consultations']);
        $payload['citizenId'] = 'u5';
        $this->expectException(DomainException::class);
        $this->apply('territory.revise', $payload);
    }

    public function testRevisionCanCiteOnlyAnObservationOfItsOwnExperiment(): void
    {
        $this->apply('proposal.revise', [
            'citizenId' => 'u4',
            'proposalId' => 'p2',
            'reason' => 'Révision après observation pédagogique',
            'observationId' => 'o1',
        ]);
        self::assertSame(
            'o1',
            $this->state['proposals'][1]['revisions'][0]['observationId'],
        );
        $this->expectException(DomainException::class);
        $this->apply('proposal.revise', [
            'citizenId' => 'u7',
            'proposalId' => 'p3',
            'reason' => 'Tentative de rattachement étranger',
            'observationId' => 'o1',
        ]);
    }

    public function testQuorumBoundaryAbstentionAndStrictMajority(): void
    {
        $consultation = $this->state['consultations'][1];
        $consultation['eligibleIds'] = ['a', 'b', 'c', 'd'];
        $consultation['rules']['quorum'] = 50;
        $votes = [
            ['consultationId' => $consultation['id'], 'choice' => 'pour'],
            ['consultationId' => $consultation['id'], 'choice' => 'abstention'],
        ];
        $tally = $this->engine->tally($consultation, $votes);
        self::assertTrue($tally['adopted']);
        self::assertSame(1, $tally['expressed']);
        $consultation['rules']['quorum'] = 50.01;
        self::assertFalse(
            $this->engine->tally($consultation, $votes)['adopted'],
        );
        $consultation['rules']['quorum'] = 0;
        $votes[1]['choice'] = 'contre';
        self::assertFalse(
            $this->engine->tally($consultation, $votes)['adopted'],
        );
        $votes[0]['choice'] = 'abstention';
        $votes[1]['choice'] = 'abstention';
        self::assertFalse(
            $this->engine->tally($consultation, $votes)['adopted'],
        );
    }

    public function testModerationNeedsIndependentConfirmationAndPreservesVoting(): void
    {
        $this->apply('moderation.report', [
            'citizenId' => 'u1',
            'objectId' => 'u4',
            'reason' => 'Menaces fictives à vérifier',
        ]);
        $case = end($this->state['cases'])['id'];
        $this->apply('moderation.decide', [
            'citizenId' => 'u2',
            'caseId' => $case,
            'targetId' => 'u4',
            'rule' => 'threat',
            'measure' => 'publication_pause',
            'days' => 2,
            'facts' => 'Menaces fictives établies pour le test',
            'evidence' => 'Pièce fictive numéro quatre, source de test',
            'reason' => 'Pause proportionnée de deux jours',
        ]);
        self::assertArrayNotHasKey(
            'publicationRestrictions',
            $this->state['citizens'][3],
        );
        $this->apply('moderation.confirm', [
            'citizenId' => 'u5',
            'caseId' => $case,
            'reason' => 'Examen indépendant des pièces fictives',
        ]);
        self::assertFalse($this->state['citizens'][3]['suspended']);
        self::assertCount(
            1,
            $this->state['citizens'][3]['publicationRestrictions'],
        );
        $this->apply('moderation.appeal', [
            'citizenId' => 'u4',
            'caseId' => $case,
            'reason' => 'Élément contradictoire fictif documenté',
        ]);
        $appeal = end($this->state['appeals'])['id'];
        $this->apply('moderation.resolve', [
            'citizenId' => 'u3',
            'appealId' => $appeal,
            'outcome' => 'revoked',
            'reason' => 'Les nouvelles pièces justifient la révocation',
        ]);
        self::assertTrue(
            $this->state['citizens'][3]['publicationRestrictions'][0][
                'revoked'
            ],
        );
        self::assertSame('revoked', end($this->state['appeals'])['status']);
        self::assertTrue($this->engine->verify($this->state)['valid']);
    }

    public function testAnOpinionIsNotAModerationRule(): void
    {
        $this->apply('moderation.report', [
            'citizenId' => 'u1',
            'objectId' => 'u4',
            'reason' => 'Opinion fictive minoritaire',
        ]);
        $this->expectException(DomainException::class);
        $this->apply('moderation.decide', [
            'citizenId' => 'u2',
            'caseId' => end($this->state['cases'])['id'],
            'targetId' => 'u4',
            'rule' => 'different_opinion',
            'measure' => 'warning',
            'days' => 1,
        ]);
    }

    public function testOrdinaryCitizenCannotConfirmOrEnact(): void
    {
        $this->expectException(DomainException::class);
        $this->apply('governance.enact', [
            'citizenId' => 'u1',
            'governanceId' => 'anything',
            'reason' => 'Tentative non autorisée',
        ]);
    }

    public function testGovernanceAppliesOnlyToFutureConsultations(): void
    {
        $before = $this->state['consultations'];
        $this->apply('governance.propose', [
            'citizenId' => 'u1',
            'title' => 'Quorum fictif à soixante pour cent',
            'reason' => 'Proposition motivée pour les futurs scrutins',
            'risk' => 'Participation insuffisante à étudier',
            'rollback' => 'Nouvelle règle sous procédure identique',
            'quorum' => 60,
            'changeAllowed' => false,
        ]);
        $id = end($this->state['governance'])['id'];
        $this->apply('governance.approve', [
            'citizenId' => 'u2',
            'governanceId' => $id,
        ]);
        $this->apply('governance.approve', [
            'citizenId' => 'u3',
            'governanceId' => $id,
        ]);
        $this->apply('governance.enact', [
            'citizenId' => 'u6',
            'governanceId' => $id,
            'reason' => 'Deux avis reçus, application prospective',
        ]);
        self::assertSame($before, $this->state['consultations']);
        $this->apply('consultation.open', [
            'citizenId' => 'u7',
            'proposalId' => 'p3',
            'days' => 14,
            'quorum' => 0,
        ]);
        self::assertSame(
            60.0,
            end($this->state['consultations'])['rules']['quorum'],
        );
        self::assertFalse(
            end($this->state['consultations'])['rules']['changeAllowed'],
        );
    }

    public function testCancellationRequiresTwoIndependentReviews(): void
    {
        $this->apply('consultation.review', [
            'citizenId' => 'u3',
            'consultationId' => 'c2',
            'reason' => 'Incident fictif affectant le scrutin',
            'proof' => 'Reproduction fictive et empreinte disponible',
        ]);
        self::assertSame('closed', $this->state['consultations'][1]['status']);
        $this->apply('consultation.review', [
            'citizenId' => 'u6',
            'consultationId' => 'c2',
            'reason' => 'Deuxième examen de l’incident',
            'proof' => 'Reproduction indépendante de test',
        ]);
        self::assertSame(
            'cancelled',
            $this->state['consultations'][1]['status'],
        );
        self::assertFalse(
            $this->engine->tally(
                $this->state['consultations'][1],
                $this->state['votes'],
            )['adopted'],
        );
        self::assertArrayHasKey(
            'previousResult',
            $this->state['consultations'][1],
        );
    }

    public function testAllFourConvergenceScenariosAreComputed(): void
    {
        $analyzer = new ConvergenceAnalyzer();
        $first = $this->state['proposals'][0];
        $second = $first;
        $first['createdAt'] = $second['createdAt'] = '2026-01-01T00:00:00Z';
        $second['id'] = 'independent';
        $first['revisions'] = $second['revisions'] = [];
        $state = $this->state;
        $state['proposals'] = [$first, $second];
        self::assertSame(
            'convergence complète',
            $analyzer->analyze($state)[0]['scenario'],
        );
        $state['proposals'][1]['solution'] = 'Une solution fictive autonome';
        self::assertSame(
            'convergence partielle',
            $analyzer->analyze($state)[0]['scenario'],
        );
        $state['proposals'][1]['indicator'] = 'Autre indicateur non comparable';
        $state['proposals'][1]['tags'] = [];
        self::assertSame(
            'absence de convergence',
            $analyzer->analyze($state)[0]['scenario'],
        );
        $state['proposals'][1]['revisions'] = [
            [
                'at' => '2026-10-28T00:00:00Z',
                'version' => 1,
                'snapshot' => $second,
            ],
        ];
        self::assertSame(
            'convergence suivie de divergence',
            $analyzer->analyze($state)[0]['scenario'],
        );
        self::assertArrayNotHasKey(
            'citizenScore',
            $analyzer->analyze($state)[0],
        );
    }

    public function testReformulationNeverReplacesFrozenReference(): void
    {
        $before = $this->state['consultations'][0]['frozen'];
        $this->apply('proposal.reformulate', [
            'citizenId' => 'u1',
            'proposalId' => 'p1',
            'locale' => 'en_GB',
            'level' => 'Plain language',
            'text' => 'Fictional explanation, explicitly validated by the author.',
            'glossary' => 'Quorum means minimum participation.',
        ]);
        self::assertSame($before, $this->state['consultations'][0]['frozen']);
        self::assertSame(
            1,
            $this->state['proposals'][0]['reformulations'][0][
                'referenceVersion'
            ],
        );
    }

    public function testEncryptedBackupCanBeRestoredAndRecounted(): void
    {
        $database = $this->temporary('.sqlite');
        copy(dirname(__DIR__) . '/data/populorum-demo.sqlite', $database);
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $database,
        ]);
        $this->apply('citizen.withdraw', ['citizenId' => 'u1']);
        $connection->insert('simulations', [
            'id' => 'backup-test',
            'revision' => 1,
            'state' => json_encode($this->state),
            'updated_at' => gmdate('c'),
        ]);
        $service = new BackupService($this->engine);
        $archive = $this->temporary('.enc');
        $restored = $this->temporary('.sqlite');
        $key = random_bytes(32);
        $service->create($connection, $archive, $key);
        self::assertStringNotContainsString(
            'PRIVATE KEY',
            file_get_contents($archive),
        );
        self::assertSame(
            1,
            $service->restore($archive, $restored, $key)['simulationsVerified'],
        );
        $restoredConnection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $restored,
        ]);
        $restoredState = json_decode(
            $restoredConnection->fetchOne(
                'SELECT state FROM simulations WHERE id = ?',
                ['backup-test'],
            ),
            true,
        );
        self::assertSame(
            $this->engine->tally(
                $this->state['consultations'][0],
                $this->state['votes'],
            ),
            $this->engine->tally(
                $restoredState['consultations'][0],
                $restoredState['votes'],
            ),
        );
        $connection->close();
        $restoredConnection->close();
        $this->expectException(RuntimeException::class);
        $service->restore(
            $archive,
            $this->temporary('.sqlite'),
            random_bytes(32),
        );
    }

    public function testReplicaCopiesAreSignedPinnedAndExtendOnly(): void
    {
        $this->apply('citizen.withdraw', ['citizenId' => 'u1']);
        $protocol = new ReplicaProtocol($this->engine);
        $first = $protocol->publish($this->state);
        self::assertTrue(
            $protocol->accept($first, $first['authority'], null)['valid'],
        );
        $this->apply('citizen.withdraw', ['citizenId' => 'u2']);
        $second = $protocol->publish($this->state);
        self::assertTrue(
            $protocol->accept($second, $first['authority'], $first)['valid'],
        );
        self::assertStringNotContainsString(
            'PRIVATE KEY',
            json_encode($second),
        );
        $this->expectException(DomainException::class);
        $protocol->accept($first, $first['authority'], $second);
    }

    public function testReplicaRejectsUnsignedEventAndPrivateData(): void
    {
        $this->apply('citizen.withdraw', ['citizenId' => 'u1']);
        $protocol = new ReplicaProtocol($this->engine);
        $copy = $protocol->publish($this->state);
        $copy['state']['events'][0]['signature'] = '';
        $this->expectException(DomainException::class);
        $protocol->verify($copy, $copy['authority']);
    }

    public function testMalformedProofIsNeverAcceptedAsAnEmptyGenesis(): void
    {
        self::assertFalse($this->engine->verify([])['valid']);
        $bad = $this->state;
        $bad['events'] = ['not-an-event'];
        self::assertFalse($this->engine->verify($bad)['valid']);
    }

    public function testPrivatePayloadNeverLeavesThroughReplication(): void
    {
        $this->state['citizens'][0]['passwordHash'] = 'SENTINELLE-PRIVEE';
        $this->apply('citizen.withdraw', ['citizenId' => 'u1']);
        $this->expectException(DomainException::class);
        new ReplicaProtocol($this->engine)->publish($this->state);
    }

    public function testVoluntaryAgentIsDisabledByDefault(): void
    {
        $path = $this->temporary('.json');
        file_put_contents(
            $path,
            json_encode([
                'url' => 'https://example.org/copy',
                'directory' => dirname($path),
                'authority' => str_repeat('a', 64),
            ]),
        );
        $agent = new VoluntaryAgent(new ReplicaProtocol($this->engine));
        self::assertFalse($agent->configuration($path)['enabled']);
        $this->expectException(RuntimeException::class);
        $agent->synchronize($path);
    }
}
