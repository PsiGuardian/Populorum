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
        $token = (new DOMXPath($document))->evaluate('string(//input[@name="_csrf_token"]/@value)');
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
