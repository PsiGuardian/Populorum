<?php

declare(strict_types=1);

use App\Service\ReplicaProtocol;
use App\Service\SimulationEngine;
use App\Service\VoluntaryAgent;
use PHPUnit\Framework\TestCase;

final class ReplicaTransportTest extends TestCase
{
    private string $root;
    private mixed $server = null;
    private array $config;

    protected function setUp(): void
    {
        $this->root =
            dirname(__DIR__) .
            '/var/replica-contract-' .
            bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        mkdir($this->root . '/web', 0700);
        $engine = new SimulationEngine();
        $state = json_decode(
            file_get_contents(
                dirname(__DIR__) . '/data/reference-scenario.json',
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $state['keyPair'] = null;
        $state = $engine->apply(
            $state,
            'citizen.withdraw',
            ['citizenId' => 'u1'],
            bin2hex(random_bytes(16)),
        );
        $copy = new ReplicaProtocol($engine)->publish($state);
        file_put_contents($this->root . '/web/copy.json', json_encode($copy));
        $this->config = [
            'enabled' => true,
            'directory' => $this->root . '/copy-a',
            'authority' => $copy['authority'],
            'maxBytesPerSecond' => 10000000,
            'authorizedUplinkBytesPerSecond' => 100000000,
            'activePercent' => 10,
            'maxCopyBytes' => 1000000,
            'monthlyBytes' => 10000000,
        ];
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->root,
                FilesystemIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir()
                ? rmdir($file->getPathname())
                : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    private function configFile(string $name = 'config.json'): string
    {
        $path = $this->root . '/' . $name;
        file_put_contents($path, json_encode($this->config));

        return $path;
    }

    private function startTlsServer(): void
    {
        $cert = $this->root . '/certificate.pem';
        $key = $this->root . '/private-key.pem';
        $process = proc_open(
            [
                'openssl',
                'req',
                '-x509',
                '-newkey',
                'rsa:2048',
                '-nodes',
                '-keyout',
                $key,
                '-out',
                $cert,
                '-days',
                '1',
                '-subj',
                '/CN=127.0.0.1',
                '-addext',
                'subjectAltName=IP:127.0.0.1',
            ],
            [
                1 => ['file', $this->root . '/certificate.log', 'w'],
                2 => ['file', $this->root . '/certificate.log', 'a'],
            ],
            $pipes,
            null,
            array_merge(getenv(), ['LD_LIBRARY_PATH' => '']),
        );
        self::assertIsResource($process);
        self::assertSame(0, proc_close($process));
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $message);
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->server = proc_open(
            [
                'openssl',
                's_server',
                '-quiet',
                '-WWW',
                '-accept',
                $address,
                '-cert',
                $cert,
                '-key',
                $key,
            ],
            [
                1 => ['file', $this->root . '/server.log', 'w'],
                2 => ['file', $this->root . '/server.log', 'a'],
            ],
            $pipes,
            $this->root . '/web',
            array_merge(getenv(), ['LD_LIBRARY_PATH' => '']),
        );
        self::assertIsResource($this->server);
        for ($attempt = 0; $attempt < 30; ++$attempt) {
            $ready = @stream_socket_client(
                'tcp://' . $address,
                $errno,
                $message,
                0.1,
            );
            if ($ready) {
                fclose($ready);
                break;
            }
            usleep(50000);
        }
        $this->config['url'] = 'https://' . $address . '/copy.json';
        $this->config['caFile'] = $cert;
    }

    public function testTwoCopiesSynchronizeOverVerifiedLocalHttpsAndResume(): void
    {
        $this->startTlsServer();
        $agent = new VoluntaryAgent(
            new ReplicaProtocol(new SimulationEngine()),
        );
        $file = $this->configFile();
        self::assertTrue($agent->synchronize($file)['valid']);
        $this->config['directory'] = $this->root . '/copy-b';
        self::assertTrue(
            $agent->synchronize($this->configFile('second.json'))['valid'],
        );
        self::assertSame(
            file_get_contents($this->root . '/copy-a/replica.json'),
            file_get_contents($this->root . '/copy-b/replica.json'),
        );
        self::assertTrue($agent->synchronize($file)['unchanged']);
        file_put_contents($this->root . '/copy-a/STOP', 'stop');
        try {
            $agent->synchronize($file);
            self::fail('STOP doit arrêter l’agent.');
        } catch (RuntimeException) {
            self::assertFileExists($this->root . '/copy-a/replica.json');
        }
        unlink($this->root . '/copy-a/STOP');
        self::assertTrue($agent->synchronize($file)['valid']);
    }

    public function testCorruptCopyNeverReplacesTheLastHealthyFile(): void
    {
        $payload = file_get_contents($this->root . '/web/copy.json');
        $this->config['url'] = 'https://authorized.example/copy';
        $source = static function () use (&$payload) {
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, $payload);
            rewind($stream);

            return $stream;
        };
        $agent = new VoluntaryAgent(
            new ReplicaProtocol(new SimulationEngine()),
            $source(...),
        );
        $file = $this->configFile();
        $agent->synchronize($file);
        $before = file_get_contents($this->root . '/copy-a/replica.json');
        $payload = '{"protocol":"corrupted"}';
        try {
            $agent->synchronize($file);
            self::fail('Une copie corrompue doit être refusée.');
        } catch (DomainException) {
            self::assertSame(
                $before,
                file_get_contents($this->root . '/copy-a/replica.json'),
            );
        }
    }

    public function testDamagedVerifierIsRestoredFromHealthyCopyAndSignedCheckpoint(): void
    {
        $payload = file_get_contents($this->root . '/web/copy.json');
        $this->config['url'] = 'https://authorized.example/copy';
        $source = static function () use ($payload) {
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, $payload);
            rewind($stream);

            return $stream;
        };
        $agent = new VoluntaryAgent(
            new ReplicaProtocol(new SimulationEngine()),
            $source(...),
        );
        $file = $this->configFile();
        $agent->synchronize($file);
        file_put_contents(
            $this->root . '/copy-a/replica.json',
            'copie locale corrompue',
        );
        self::assertTrue($agent->synchronize($file)['valid']);
        self::assertSame(
            json_decode($payload, true),
            json_decode(
                file_get_contents($this->root . '/copy-a/replica.json'),
                true,
            ),
        );
    }

    public function testConfiguredBandwidthIsActuallyPaced(): void
    {
        $payload = file_get_contents($this->root . '/web/copy.json');
        $this->config['url'] = 'https://authorized.example/copy';
        $this->config['maxBytesPerSecond'] = 100000;
        $source = static function () use ($payload) {
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, $payload);
            rewind($stream);

            return $stream;
        };
        $agent = new VoluntaryAgent(
            new ReplicaProtocol(new SimulationEngine()),
            $source(...),
        );
        $start = microtime(true);
        $result = $agent->synchronize($this->configFile());
        self::assertGreaterThanOrEqual(
            $result['receivedBytes'] / 100000 - 0.03,
            microtime(true) - $start,
        );
    }

    public function testVolumeLimitDoesNotConsumeMoreThanAuthorizedPayload(): void
    {
        $payload = file_get_contents($this->root . '/web/copy.json');
        $this->config['url'] = 'https://authorized.example/copy';
        $this->config['monthlyBytes'] = 64;
        $source = static function () use ($payload) {
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, $payload);
            rewind($stream);

            return $stream;
        };
        $agent = new VoluntaryAgent(
            new ReplicaProtocol(new SimulationEngine()),
            $source(...),
        );
        try {
            $agent->synchronize($this->configFile());
            self::fail('Le plafond doit interrompre le transfert.');
        } catch (RuntimeException) {
            $usage = json_decode(
                file_get_contents(
                    $this->root . '/copy-a/usage-' . gmdate('Y-m') . '.json',
                ),
                true,
            );
            self::assertSame(64, $usage['bytes']);
            self::assertFileDoesNotExist($this->root . '/copy-a/replica.json');
        }
    }
}
