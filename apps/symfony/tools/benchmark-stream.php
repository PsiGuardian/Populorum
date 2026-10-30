<?php

declare(strict_types=1);

/** Deliberately measure one legacy SSE stream; no browser, users or external network. */
require __DIR__ . '/bootstrap.php';
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$database =
    dirname(__DIR__) . '/var/perf-' . bin2hex(random_bytes(8)) . '.sqlite';
copy(dirname(__DIR__) . '/data/populorum-demo.sqlite', $database);
$_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///' . $database;
$_SERVER['POPULORUM_SCENARIO'] = '';
$kernel = new App\Kernel('demo', false);
$session = new Session(new MockArraySessionStorage());
$request = Request::create('https://bench.example/api/v1/events');
$request->setSession($session);
try {
    $response = $kernel->handle($request);
    $bytes = 0;
    ob_start(static function (string $part) use (&$bytes): string {
        $bytes += strlen($part);

        return '';
    });
    $start = hrtime(true);
    $response->sendContent();
    $duration = (hrtime(true) - $start) / 1e6;
    ob_end_clean();
    $kernel->terminate($request, $response);
    echo json_encode(
        [
            'endpoint' => '/api/v1/events',
            'streamMs' => round($duration, 3),
            'bytes' => $bytes,
            'scope' => 'Temps d’occupation du worker par un flux SSE historique, hors en-têtes et réseau. Cette route reste compatible mais le navigateur utilise désormais des requêtes courtes.',
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
    ) . PHP_EOL;
} finally {
    $kernel->shutdown();
    foreach ([$database, $database . '-wal', $database . '-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
