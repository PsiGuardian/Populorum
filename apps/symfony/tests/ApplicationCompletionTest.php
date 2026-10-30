<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/tools/bootstrap.php';

use App\Message\AuditEventRecorded;
use App\MessageHandler\AuditEventHandler;
use App\Service\DemoDatabase;
use App\Service\RelationalProjection;
use App\Service\SimulationEngine;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class ApplicationCompletionTest extends TestCase
{
    private App\Kernel $kernel;
    private Session $session;
    private string $database;
    private Doctrine\DBAL\Connection $connection;
    private DemoDatabase $store;

    protected function setUp(): void
    {
        $this->database =
            dirname(__DIR__) .
            '/var/application-contract-' .
            bin2hex(random_bytes(8)) .
            '.sqlite';
        copy(dirname(__DIR__) . '/data/populorum-demo.sqlite', $this->database);
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] =
            'sqlite:///' . $this->database;
        $_SERVER['POPULORUM_REQUIRE_LOGIN'] = '0';
        $_SERVER['POPULORUM_SCENARIO'] = 'reference';
        $this->kernel = new App\Kernel('demo', false);
        $this->session = new Session(new MockArraySessionStorage());
        $this->request('/');
        $this->connection = $this->kernel
            ->getContainer()
            ->get('doctrine')
            ->getConnection();
        $this->store = new DemoDatabase(
            $this->connection,
            dirname(__DIR__),
            new RelationalProjection($this->connection),
        );
    }

    protected function tearDown(): void
    {
        $_SERVER['POPULORUM_REQUIRE_LOGIN'] = '0';
        $_SERVER['POPULORUM_SCENARIO'] = 'reference';
        $this->kernel->shutdown();
        if (is_file($this->database)) {
            unlink($this->database);
        }
    }

    private function request(
        string $path,
        string $method = 'GET',
        array $data = [],
        array $headers = [],
        ?string $content = null,
    ): Response {
        $request = Request::create(
            'https://demo.example' . $path,
            $method,
            $data,
            [$this->session->getName() => $this->session->getId()],
            [],
            ['HTTPS' => 'on'],
            $content,
        );
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }
        $request->setSession($this->session);
        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        return $response;
    }

    public function testReasoningWorkbenchAndRealContextAreSeparateAndUsable(): void
    {
        $home = $this->request('/')->getContent();
        self::assertStringContainsString(
            'Du problème aux conséquences.',
            $home,
        );
        self::assertStringContainsString('Arbre de cause à effet', $home);
        self::assertStringContainsString('système d’État déjà en place', $home);
        self::assertStringNotContainsString(
            'Afficher le graphe des relations déclarées',
            $home,
        );
        $convergence = $this->request(
            '/convergence?id=p1&indicator=Cambriolages%20de%20logement',
        );
        self::assertSame(200, $convergence->getStatusCode());
        self::assertStringContainsString(
            'Taux pour 1 000 logements',
            $convergence->getContent(),
        );
        self::assertStringContainsString(
            'Évolutions de',
            $convergence->getContent(),
        );
        self::assertStringContainsString(
            'Résultats fictifs',
            $convergence->getContent(),
        );
        self::assertStringContainsString(
            'value="p1"',
            $convergence->getContent(),
        );
        $form = $this->request('/action/proposal.create')->getContent();
        self::assertStringContainsString('1 · Décrire le chemin', $form);
        self::assertSame(12, substr_count($form, 'class="reasoning-field"'));
        self::assertStringContainsString('simulation_action[_token]', $form);
        self::assertStringContainsString(
            'système d’État déjà en place',
            $this->request('/charter')->getContent(),
        );
    }

    public function testSearchUsesNamedRoutesAndRejectsMalformedInputs(): void
    {
        $legacy = $this->request('/proposals?q=find&territory=all');
        self::assertSame(302, $legacy->getStatusCode());
        self::assertSame(
            '/recherche/proposals/all/find',
            $legacy->headers->get('Location'),
        );
        self::assertSame(
            200,
            $this->request('/recherche/proposals/all/find')->getStatusCode(),
        );
        self::assertSame(
            200,
            $this->request('/proposals/fiche/p1')->getStatusCode(),
        );
        foreach (
            [
                '/proposals?q%5B%5D=find',
                '/proposals?q=' . str_repeat('x', 161),
                '/proposals?territory=t99',
                '/api/v1/proposals?q%5B%5D=x',
            ] as $url
        ) {
            self::assertSame(400, $this->request($url)->getStatusCode());
        }
        self::assertSame(
            403,
            $this->request('/recherche', 'POST', [
                'view' => 'proposals',
                'q' => 'find',
            ])->getStatusCode(),
        );
        $html = $this->request('/chemins')->getContent();
        $document = new DOMDocument();
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $token = $xpath->evaluate(
            'string(//form[@action="/recherche"]//input[@name="_token"]/@value)',
        );
        self::assertNotEmpty($token);
        $response = $this->request('/recherche', 'POST', [
            '_token' => $token,
            'view' => 'proposals',
            'q' => "' OR 1=1 --",
            'territory' => 'all',
        ]);
        self::assertSame(303, $response->getStatusCode());
        $result = $this->request($response->headers->get('Location'));
        self::assertSame(200, $result->getStatusCode());
        self::assertStringContainsString('Aucun chemin', $result->getContent());
    }

    public function testAccountsPageExplainsPublicFictionAndRendersStatistics(): void
    {
        $response = $this->request('/recits');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(
            'sans promesse d’immunité',
            $response->getContent(),
        );
        self::assertStringContainsString(
            'Cambriolages de logement',
            $response->getContent(),
        );
        self::assertStringContainsString(
            'Les homicides',
            $response->getContent(),
        );
        self::assertSame(
            200,
            $this->request(
                '/action/account.create?proposalId=p1',
            )->getStatusCode(),
        );
    }

    public function testPublicNarrativeIsPersistedEscapedAndAppendOnlyThroughApi(): void
    {
        $session = $this->apiSession();
        $payload = [
            'title' => '<script>alert(1)</script>',
            'category' => 'Inquiétude fictive',
            'kind' => 'concern',
            'proposalId' => 'p1',
            'nature' => 'hypothesis',
            'disclosure' => 'partial',
            'cause' => '<img src=x onerror=alert(1)>',
            'consequence' => 'Le personnage hésite à parler.',
            'uncertainty' => 'Il manque des éléments pour conclure.',
            'reason' => 'Commencer progressivement.',
            'fictionalConsent' => true,
        ];
        $body = [
            'revision' => $session['revision'],
            'operationId' => bin2hex(random_bytes(16)),
            'payload' => $payload,
        ];
        $headers = [
            'X-CSRF-Token' => $session['csrfToken'],
            'Content-Type' => 'application/json',
        ];
        self::assertSame(
            403,
            $this->request(
                '/api/v1/actions/account.create',
                'POST',
                content: json_encode($body),
            )->getStatusCode(),
        );
        self::assertSame(
            200,
            $this->request(
                '/api/v1/actions/account.create',
                'POST',
                headers: $headers,
                content: json_encode($body),
            )->getStatusCode(),
        );
        $owner = $this->session->get('simulation_owner');
        $saved = $this->store->workspace($owner);
        $account = $saved['state']['causalAccounts'][0];
        self::assertSame('u1', $account['authorId']);
        $page = $this->request(
            '/accounts/fiche/' . $account['id'],
        )->getContent();
        self::assertStringNotContainsString('<script>alert(1)</script>', $page);
        self::assertStringContainsString('&lt;script&gt;', $page);
        self::assertStringContainsString('&lt;img', $page);
        $form = $this->request(
            '/agir/account.append/accountId/' . $account['id'],
        );
        self::assertSame(200, $form->getStatusCode());
        self::assertStringContainsString(
            'value="' . $account['id'] . '"',
            $form->getContent(),
        );
        $first = $account['entries'][0];
        $body = [
            'revision' => $saved['revision'],
            'operationId' => bin2hex(random_bytes(16)),
            'payload' => array_diff_key(
                $payload,
                array_flip(['title', 'category', 'kind', 'proposalId']),
            ) + ['accountId' => $account['id']],
        ];
        self::assertSame(
            200,
            $this->request(
                '/api/v1/actions/account.append',
                'POST',
                headers: $headers,
                content: json_encode($body),
            )->getStatusCode(),
        );
        $saved = $this->store->workspace($owner);
        self::assertCount(2, $saved['state']['causalAccounts'][0]['entries']);
        self::assertSame(
            $first,
            $saved['state']['causalAccounts'][0]['entries'][0],
        );
        self::assertTrue(
            new SimulationEngine()->verify($saved['state'])['valid'],
        );
        self::assertSame(
            409,
            $this->request(
                '/api/v1/actions/account.append',
                'POST',
                headers: $headers,
                content: json_encode($body),
            )->getStatusCode(),
        );
    }

    public function testFivefoldListsPreserveNavigationFiltersAndExactVisibleTallies(): void
    {
        require_once dirname(__DIR__) . '/tools/scale-fixture.php';
        $_SERVER['POPULORUM_SCENARIO'] = '';
        $owner = 'scale-http-' . bin2hex(random_bytes(8));
        $this->session->set('simulation_owner', $owner);
        $original = $this->store->workspace($owner);
        $state = performanceScaleFixture($original['state'], 5);
        $this->store->save($owner, 0, $state);
        self::assertTrue(new SimulationEngine()->verify($state)['valid']);
        self::assertCount(1500, $state['citizens']);
        self::assertCount(13500, $state['votes']);
        $seen = [];
        for ($page = 1; $page <= 5; ++$page) {
            $response = $this->request('/liste/consultations/all/' . $page);
            self::assertSame(200, $response->getStatusCode());
            preg_match_all('/data-consultation-id="([^"]+)"/', $response->getContent(), $matches);
            self::assertCount(27, $matches[1]);
            $seen = array_merge($seen, $matches[1]);
            self::assertStringContainsString('sur 135 résultat', $response->getContent());
            self::assertLessThan(250000, strlen($response->getContent()));
        }
        self::assertSame(array_column($state['consultations'], 'id'), $seen);
        $filtered = $this->request('/liste/consultations/t2/1');
        self::assertStringContainsString('sur 45 résultat', $filtered->getContent());
        $search = $this->request('/liste/proposals/t1/1/' . rawurlencode($state['proposals'][0]['title']));
        self::assertSame(200, $search->getStatusCode());
        self::assertStringContainsString('sur 5 résultat', $search->getContent());
        $workers = $this->request('/liste/services/all/2?skill=water');
        self::assertSame(200, $workers->getStatusCode());
        self::assertStringContainsString('skill=water', $workers->getContent());
        self::assertLessThan(100000, strlen($workers->getContent()));
        $live = $this->request('/api/v1/live-results?ids=c1,c1_load5');
        $data = json_decode($live->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['c1', 'c1_load5'], array_column($data['consultations'], 'id'));
        foreach ($data['consultations'] as $row) {
            $consultation = array_column($state['consultations'], null, 'id')[$row['id']];
            $expected = new SimulationEngine()->tally($consultation, $state['votes']);
            self::assertSame($row['tally'], array_intersect_key($expected, $row['tally']));
        }
        self::assertSame(400, $this->request('/api/v1/live-results?ids=' . implode(',', range(1, 28)))->getStatusCode());
        self::assertSame(400, $this->request('/api/v1/live-results?ids[]=c1')->getStatusCode());
        self::assertSame($state, $this->store->workspace($owner)['state']);
    }

    public function testFastPagesKeepDetailedVotesFullExportsAndShortLiveResults(): void
    {
        $_SERVER['POPULORUM_SCENARIO'] = '';
        $this->session->set(
            'simulation_owner',
            'performance-http-' . bin2hex(random_bytes(8)),
        );
        $_SERVER['POPULORUM_TIMING'] = '1';
        try {
            $list = $this->request('/consultations');
            self::assertSame(200, $list->getStatusCode());
            self::assertSame(
                27,
                substr_count($list->getContent(), 'data-consultation-id='),
            );
            self::assertLessThan(500000, strlen($list->getContent()));
            self::assertStringNotContainsString(
                'Participations nominatives fictives',
                $list->getContent(),
            );
            self::assertStringContainsString(
                'Voir les votes, les familles et le texte figé',
                $list->getContent(),
            );
            $detail = $this->request('/consultations/fiche/c1');
            self::assertStringContainsString(
                'Participations nominatives fictives',
                $detail->getContent(),
            );
            self::assertStringContainsString(
                'Décompte des familles et règle figée',
                $detail->getContent(),
            );
            $home = $this->request('/');
            self::assertStringContainsString(
                'db_read;dur=',
                $home->headers->get('Server-Timing'),
            );
            self::assertStringNotContainsString(
                'convergence;dur=',
                $home->headers->get('Server-Timing'),
            );
            $live = $this->request('/api/v1/live-results');
            self::assertSame(200, $live->getStatusCode());
            self::assertStringContainsString(
                'application/json',
                $live->headers->get('Content-Type'),
            );
            self::assertStringContainsString(
                'no-store',
                $live->headers->get('Cache-Control'),
            );
            $data = json_decode(
                $live->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            self::assertCount(27, $data['consultations']);
            self::assertLessThan(20000, strlen($live->getContent()));
            foreach ($data['consultations'] as $consultation) {
                self::assertSame(['id', 'tally'], array_keys($consultation));
                self::assertArrayNotHasKey('families', $consultation['tally']);
            }
            $export = json_decode(
                $this->request('/export/memory')->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            self::assertArrayHasKey('mindHistory', $export['citizens'][0]);
            self::assertArrayHasKey('deliberation', $export['votes'][0]);
            self::assertTrue(new SimulationEngine()->verify($export)['valid']);
        } finally {
            unset($_SERVER['POPULORUM_TIMING']);
        }
        self::assertFalse($this->request('/')->headers->has('Server-Timing'));
    }

    public function testNativeProcurementFormConvertsEurosToIntegerCentsAndReturnsToDossier(): void
    {
        $response = $this->request('/action/tender.create');
        $document = new DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $fields = [];
        foreach ($xpath->query('//input[@type="hidden"]') as $input) {
            if (
                preg_match(
                    '/^simulation_action\[([^]]+)\]$/',
                    $input->getAttribute('name'),
                    $match,
                )
            ) {
                $fields[$match[1]] = $input->getAttribute('value');
            }
        }
        $fields = array_replace($fields, [
            'title' => 'Test de saisie monétaire',
            'scope' => 'Même périmètre fictif.',
            'location' => 'Place fictive',
            'service' => 'maintenance',
            'risks' => 'Travail au sol.',
            'cause' => 'Usure supposée.',
            'expectedEffect' => 'État amélioré.',
            'indicator' => 'Nombre contrôlé',
            'uncertainty' => 'Cause incertaine.',
            'proposalId' => '',
            'missionId' => '',
            'deadline' => new DateTimeImmutable(
                '+2 days',
                new DateTimeZone('Europe/Paris'),
            )->format('Y-m-d H:i'),
            'budgetCents' => '30000.50',
            'referenceCents' => '3000.25',
            'referenceBasis' => 'Comparaison fictive.',
            'horizonYears' => '10',
        ]);
        $response = $this->request('/action/tender.create', 'POST', [
            'simulation_action' => $fields,
        ]);
        self::assertSame(
            302,
            $response->getStatusCode(),
            $response->getContent(),
        );
        $saved = $this->store->workspace(
            $this->session->get('simulation_owner'),
        );
        $tender = $saved['state']['tenders'][0];
        self::assertSame(3000050, $tender['budgetCents']);
        self::assertSame(300025, $tender['referenceCents']);
        self::assertSame(
            '/procurement/fiche/' . $tender['id'],
            $response->headers->get('Location'),
        );
        self::assertTrue(
            new SimulationEngine()->verify($saved['state'])['valid'],
        );
    }

    public function testProcurementRoutesSealedExportsAndPostControls(): void
    {
        self::assertSame(
            200,
            $this->request('/besoins-et-devis')->getStatusCode(),
        );
        self::assertSame(
            200,
            $this->request('/besoins-et-devis/ville/t2')->getStatusCode(),
        );
        self::assertSame(
            404,
            $this->request('/besoins-et-devis/ville/t9')->getStatusCode(),
        );
        self::assertSame(
            200,
            $this->request('/action/tender.create')->getStatusCode(),
        );
        $session = $this->apiSession();
        $headers = [
            'X-CSRF-Token' => $session['csrfToken'],
            'Content-Type' => 'application/json',
        ];
        $payload = [
            'title' => '<script>PROCUREMENT_XSS</script>',
            'scope' => 'Quatre bancs de même qualité.',
            'location' => 'Lieu fictif',
            'service' => 'maintenance',
            'risks' => 'Tâche simple.',
            'cause' => 'Usure supposée.',
            'expectedEffect' => 'Bancs utilisables.',
            'indicator' => 'Quatre bancs contrôlés.',
            'uncertainty' => 'Cause non démontrée.',
            'deadline' => new DateTimeImmutable(
                '+2 days',
                new DateTimeZone('Europe/Paris'),
            )->format('Y-m-d H:i'),
            'budgetCents' => 3000000,
            'referenceCents' => 300000,
            'referenceBasis' => 'Référence fictive.',
            'horizonYears' => 10,
        ];
        $body = [
            'revision' => $session['revision'],
            'operationId' => bin2hex(random_bytes(16)),
            'payload' => $payload,
        ];
        self::assertSame(
            403,
            $this->request(
                '/api/v1/actions/tender.create',
                'POST',
                content: json_encode($body),
            )->getStatusCode(),
        );
        self::assertSame(
            200,
            $this->request(
                '/api/v1/actions/tender.create',
                'POST',
                headers: $headers,
                content: json_encode($body),
            )->getStatusCode(),
        );
        self::assertSame(
            409,
            $this->request(
                '/api/v1/actions/tender.create',
                'POST',
                headers: $headers,
                content: json_encode($body),
            )->getStatusCode(),
        );
        $owner = $this->session->get('simulation_owner');
        $saved = $this->store->workspace($owner);
        $id = $saved['state']['tenders'][0]['id'];
        $this->session->set('citizen_id', 'u2');
        $bid = [
            'tenderId' => $id,
            'reason' => 'SECRET_REASON_43871',
            'company' => 'SECRET_COMPANY_92485',
            'lines' => 'SECRET_PRICE_38712 | 1 | 300000',
            'qualifications' => 'Compétences fictives.',
            'exclusions' => 'Aucune réserve.',
            'days' => 20,
            'warrantyMonths' => 24,
            'annualMaintenanceCents' => 0,
            'fictionalConsent' => true,
        ];
        self::assertSame(
            200,
            $this->request(
                '/api/v1/actions/tender.bid',
                'POST',
                headers: $headers,
                content: json_encode([
                    'revision' => $saved['revision'],
                    'operationId' => bin2hex(random_bytes(16)),
                    'payload' => $bid,
                ]),
            )->getStatusCode(),
        );
        foreach (
            [
                '/procurement/fiche/' . $id,
                '/export/memory',
                '/export/proofs',
                '/api/v1/state',
            ] as $url
        ) {
            $response = $this->request($url);
            self::assertSame(200, $response->getStatusCode(), $url);
            foreach (
                [
                    'SECRET_REASON_43871',
                    'SECRET_COMPANY_92485',
                    'SECRET_PRICE_38712',
                ] as $secret
            ) {
                self::assertStringNotContainsString(
                    $secret,
                    $response->getContent(),
                );
            }
        }
        $page = $this->request('/procurement/fiche/' . $id)->getContent();
        self::assertStringContainsString('&lt;script&gt;', $page);
        self::assertStringNotContainsString(
            '<script>PROCUREMENT_XSS</script>',
            $page,
        );
        foreach (array_keys(App\Service\ActionForms::TYPES) as $type) {
            if (str_starts_with($type, 'tender.')) {
                $form = $this->request('/agir/' . $type . '/tenderId/' . $id);
                self::assertSame(200, $form->getStatusCode(), $type);
            }
        }
        $saved = $this->store->workspace($owner);
        $state = $saved['state'];
        new App\Service\PublicProcurement(time() + 3 * 86400)->apply(
            $state,
            'tender.open',
            [
                'tenderId' => $id,
                'reason' => 'Ouverture d’une fixture après échéance.',
            ],
            $state['citizens'][0],
        );
        $state = new SimulationEngine()->initializeFixture($state);
        $this->store->save($owner, $saved['revision'], $state);
        $page = $this->request('/procurement/fiche/' . $id)->getContent();
        self::assertStringContainsString('SECRET_COMPANY_92485', $page);
        self::assertStringContainsString('Décomposition TTC', $page);
        self::assertSame(
            200,
            $this->request('/budget-et-priorites')->getStatusCode(),
        );
    }

    public function testCityServicesAndPublicBudgetsHaveWorkingNativeRoutes(): void
    {
        $_SERVER['POPULORUM_SCENARIO'] = '';
        $this->session->set('simulation_owner', 'city-module-test');
        foreach (
            [
                '/services-et-missions',
                '/budget-et-priorites',
                '/action/worker.profile',
                '/action/worker.availability',
                '/action/mission.create',
            ] as $path
        ) {
            self::assertSame(
                200,
                $this->request($path)->getStatusCode(),
                $path,
            );
        }
        $filtered = $this->request(
            '/services-et-missions?territory=t3&skill=maintenance',
        );
        self::assertSame(200, $filtered->getStatusCode());
        self::assertSame(
            400,
            $this->request(
                '/services-et-missions?skill=invalid',
            )->getStatusCode(),
        );
        self::assertSame(
            400,
            $this->request('/budget-et-priorites?saving=101')->getStatusCode(),
        );
        self::assertSame(
            400,
            $this->request(
                '/budget-et-priorites?account=6%20OR%201=1',
            )->getStatusCode(),
        );
        self::assertSame(
            200,
            $this->request(
                '/finances-publiques/export/aggregates',
            )->getStatusCode(),
        );
        self::assertSame(
            404,
            $this->request(
                '/finances-publiques/export/secret',
            )->getStatusCode(),
        );
        $budget = $this->request(
            '/budget-et-priorites?saving=100',
        )->getContent();
        self::assertStringContainsString('Cible de 1 € non atteinte', $budget);
        self::assertStringContainsString('4 322 lignes', $budget);
    }

    private function apiSession(): array
    {
        return json_decode(
            $this->request('/api/v1/session')->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function testMunicipalFixtureRendersEveryPageAndFiltersByCity(): void
    {
        $_SERVER['POPULORUM_SCENARIO'] = '';
        $this->session->set('simulation_owner', 'municipal-test');
        foreach (
            [
                '/',
                '/territories',
                '/proposals',
                '/consultations',
                '/experiments',
                '/convergence',
                '/memory',
                '/transparency',
                '/infrastructure',
                '/governance',
                '/citizen',
                '/charter',
            ] as $path
        ) {
            self::assertSame(
                200,
                $this->request($path)->getStatusCode(),
                $path,
            );
        }
        $html = $this->request('/proposals?territory=t3')->getContent();
        self::assertSame(9, substr_count($html, 'class="proposal-card"'));
        self::assertStringNotContainsString('/ Paris</div>', $html);
        $state = $this->store->workspace('municipal-test')['state'];
        self::assertCount(300, $state['citizens']);
        self::assertSame(
            300,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM citizen_records WHERE scenario_id = ?',
                ['municipal-test'],
            ),
        );
        self::assertTrue(
            new SimulationEngine()->verify(
                new SimulationEngine()->publicState($state),
            )['valid'],
        );
        self::assertStringContainsString(
            'href="https://',
            $this->request('/proposals?id=p1')->getContent(),
        );
    }

    public function testCharacterPortraitAndAllTwelveAnswersAreAccessible(): void
    {
        $_SERVER['POPULORUM_SCENARIO'] = '';
        $this->session->set('simulation_owner', 'mental-portrait-test');
        $results = json_decode(
            $this->request('/api/v1/results?limit=1')->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('family', $results['data'][0]['tally']['unit']);
        self::assertSame(
            100,
            $results['data'][0]['tally']['individual']['total'],
        );
        self::assertLessThan(100, $results['data'][0]['tally']['total']);
        self::assertStringContainsString(
            'une famille compte au maximum une voix',
            preg_replace(
                '/\s+/u',
                ' ',
                strip_tags(
                    $this->request('/consultations?id=c1')->getContent(),
                ),
            ),
        );
        $portrait = $this->request('/characters/u1?consultation=c1');
        self::assertSame(200, $portrait->getStatusCode());
        self::assertStringContainsString(
            'Mes neuf décisions locales',
            $portrait->getContent(),
        );
        $response = $this->request(
            '/characters/u1?consultation=c1&round=0&format=json',
        );
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode(
            $response->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertCount(12, $data['reasoning']);
        self::assertSame(0, $data['round']);
        self::assertSame('u1', $data['citizenId']);
        self::assertStringNotContainsString(
            'PRIVATE KEY',
            $response->getContent(),
        );
        self::assertSame(
            404,
            $this->request(
                '/characters/u1?consultation=c10&format=json',
            )->getStatusCode(),
        );
        self::assertSame(
            404,
            $this->request('/characters/u1?round=99')->getStatusCode(),
        );
        self::assertSame(
            404,
            $this->request('/characters/unknown')->getStatusCode(),
        );
    }

    public function testStoredMarkupIsEscapedAndDoesNotBecomeExecutable(): void
    {
        $workspace = $this->store->workspace(
            $this->session->get('simulation_owner'),
        );
        $state = $workspace['state'];
        $payload = $state['proposals'][0];
        $payload['citizenId'] = 'u1';
        $payload['tags'] = 'sécurité, test';
        $payload['title'] = '<script>window.injected=true</script>';
        $state = new SimulationEngine()->apply(
            $state,
            'proposal.create',
            $payload,
            bin2hex(random_bytes(16)),
        );
        $this->store->save(
            $this->session->get('simulation_owner'),
            $workspace['revision'],
            $state,
        );
        $html = $this->request('/proposals')->getContent();
        self::assertStringContainsString(
            '&lt;script&gt;window.injected=true&lt;/script&gt;',
            $html,
        );
        self::assertStringNotContainsString('<script>window.injected', $html);
    }

    public function testResourcePaginationAndAdversarialInputAreHandled(): void
    {
        $data = json_decode(
            $this->request('/api/v1/proposals?limit=1&page=2')->getContent(),
            true,
        );
        self::assertCount(1, $data['data']);
        self::assertSame(2, $data['meta']['page']);
        self::assertSame(3, $data['meta']['total']);
        self::assertSame(
            400,
            $this->request('/api/v1/proposals?sort=password')->getStatusCode(),
        );
        $data = json_decode(
            $this->request(
                '/api/v1/proposals?q=%27%20OR%201%3D1%20--',
            )->getContent(),
            true,
        );
        self::assertSame(0, $data['meta']['total']);
        self::assertSame(
            3,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM municipalities',
            ),
        );
        self::assertStringNotContainsString(
            'PRIVATE KEY',
            $this->request('/api/v1/proofs')->getContent(),
        );
    }

    public function testJsonMutationChecksCsrfActorRevisionAndUnknownFields(): void
    {
        $session = $this->apiSession();
        $body = [
            'revision' => $session['revision'],
            'operationId' => bin2hex(random_bytes(16)),
            'payload' => ['consent' => true],
        ];
        $path = '/api/v1/actions/citizen.consent';
        self::assertSame(
            403,
            $this->request(
                $path,
                'POST',
                content: json_encode($body),
            )->getStatusCode(),
        );
        $headers = [
            'X-CSRF-Token' => $session['csrfToken'],
            'Content-Type' => 'application/json',
        ];
        $bad = $body;
        $bad['payload']['citizenId'] = 'u4';
        self::assertSame(
            400,
            $this->request(
                $path,
                'POST',
                headers: $headers,
                content: json_encode($bad),
            )->getStatusCode(),
        );
        $bad = $body;
        $bad['payload']['password'] = 'DO-NOT-PUBLISH';
        self::assertSame(
            400,
            $this->request(
                $path,
                'POST',
                headers: $headers,
                content: json_encode($bad),
            )->getStatusCode(),
        );
        self::assertSame(
            200,
            $this->request(
                $path,
                'POST',
                headers: $headers,
                content: json_encode($body),
            )->getStatusCode(),
        );
        self::assertSame(
            409,
            $this->request(
                $path,
                'POST',
                headers: $headers,
                content: json_encode($body),
            )->getStatusCode(),
        );
        $state = json_decode(
            $this->request('/api/v1/state')->getContent(),
            true,
        )['state'];
        self::assertTrue($state['citizens'][0]['consent']);
        self::assertStringNotContainsString(
            'DO-NOT-PUBLISH',
            json_encode($state),
        );
    }

    public function testSseReturnsCurrentRevisionWithoutPrivateKeys(): void
    {
        $response = $this->request('/api/v1/events?once=1');
        self::assertStringStartsWith(
            'text/event-stream',
            $response->headers->get('Content-Type'),
        );
        ob_start();
        ob_start();
        $response->sendContent();
        $inner = ob_get_clean();
        $stream = ob_get_clean() . $inner;
        self::assertStringContainsString('event: state', $stream);
        self::assertStringContainsString('"simulation":true', $stream);
        self::assertStringNotContainsString('PRIVATE KEY', $stream);
    }

    public function testSqlUniquenessRollsBackSnapshotAndProjectionTogether(): void
    {
        $owner = $this->session->get('simulation_owner');
        $workspace = $this->store->workspace($owner);
        $engine = new SimulationEngine();
        $state = $engine->apply(
            $workspace['state'],
            'citizen.withdraw',
            ['citizenId' => 'u1'],
            bin2hex(random_bytes(16)),
        );
        $this->store->save($owner, $workspace['revision'], $state);
        $before = $this->store->workspace($owner);
        $bad = $before['state'];
        $duplicate = $bad['votes'][0];
        $duplicate['id'] = 'illicit-second-vote';
        $bad['votes'][] = $duplicate;
        try {
            $this->store->save($owner, $before['revision'], $bad);
            self::fail('La contrainte SQL aurait dû refuser le doublon.');
        } catch (UniqueConstraintViolationException) {
            self::assertSame($before, $this->store->workspace($owner));
            self::assertSame(
                count($state['votes']),
                (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM vote_records WHERE scenario_id = ?',
                    [$owner],
                ),
            );
        }
    }

    public function testOutboxMessagesAreDurableAndHandlerIsIdempotent(): void
    {
        $owner = $this->session->get('simulation_owner');
        $workspace = $this->store->workspace($owner);
        $engine = new SimulationEngine();
        $state = $engine->apply(
            $workspace['state'],
            'citizen.withdraw',
            ['citizenId' => 'u1'],
            bin2hex(random_bytes(16)),
        );
        $this->store->save($owner, $workspace['revision'], $state);
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM audit_outbox WHERE dispatched = 0',
            ),
        );
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        self::assertSame(
            0,
            $application->run(
                new ArrayInput([
                    'command' => 'app:events:dispatch',
                    '--env' => 'demo',
                ]),
                $output,
            ),
        );
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM messenger_messages',
            ),
        );
        $handler = new AuditEventHandler($this->connection, $engine);
        $message = new AuditEventRecorded($owner, $state['events'][0]['id']);
        $handler($message);
        $handler($message);
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM message_receipts',
            ),
        );
    }

    public function testFictionalAccountLoginBindsItsOwnIdentity(): void
    {
        $this->store->workspace('shared');
        $this->connection->insert('civic_accounts', [
            'username' => 'contract-user',
            'password_hash' => password_hash(
                'CONTRACT-ONLY-SECRET-2026',
                PASSWORD_BCRYPT,
                ['cost' => 4],
            ),
            'workspace_id' => 'shared',
            'citizen_id' => 'u7',
            'roles' => json_encode(['ROLE_CITIZEN']),
            'enabled' => 1,
        ]);
        $login = $this->request('/citizen/login');
        self::assertSame(200, $login->getStatusCode());
        $document = new DOMDocument();
        @$document->loadHTML($login->getContent());
        $token = new DOMXPath($document)->evaluate(
            'string(//input[@name="_csrf_token"]/@value)',
        );
        self::assertNotSame('', $token);
        $response = $this->request('/citizen/login', 'POST', [
            '_username' => 'contract-user',
            '_password' => 'CONTRACT-ONLY-SECRET-2026',
            '_csrf_token' => $token,
        ]);
        self::assertTrue($response->isRedirect());
        $session = $this->apiSession();
        self::assertTrue($session['authenticated']);
        self::assertSame('u7', $session['actorId']);
        self::assertSame(
            403,
            $this->request('/persona', 'POST', [
                'citizen' => 'u1',
            ])->getStatusCode(),
        );
        self::assertStringNotContainsString(
            'password_hash',
            $this->request('/api/v1/state')->getContent(),
        );
    }

    public function testLoginRequiredModeBlocksApiWritesAndAllowsPublicReading(): void
    {
        $_SERVER['POPULORUM_REQUIRE_LOGIN'] = '1';
        self::assertSame(200, $this->request('/proposals')->getStatusCode());
        self::assertSame(
            401,
            $this->request(
                '/api/v1/actions/citizen.consent',
                'POST',
            )->getStatusCode(),
        );
        self::assertTrue(
            $this->request('/action/citizen.consent')->isRedirect(
                '/citizen/login',
            ),
        );
    }
}
