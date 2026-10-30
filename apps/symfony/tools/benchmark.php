<?php

declare(strict_types=1);

/** Mesure locale séquentielle, sans serveur HTTP ni prétention de capacité nationale. */
require __DIR__ . '/bootstrap.php';

use App\Service\DemoDatabase;
use App\Service\RelationalProjection;
use App\Service\SimulationEngine;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$path =
    dirname(__DIR__) . '/var/benchmark-' . bin2hex(random_bytes(8)) . '.sqlite';
copy(dirname(__DIR__) . '/data/populorum-demo.sqlite', $path);
$_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///' . $path;
$_SERVER['POPULORUM_SCENARIO'] = '';
$kernel = new App\Kernel('demo', false);
$session = new Session(new MockArraySessionStorage());
$session->set('simulation_owner', 'benchmark');
$call = static function () use ($kernel, $session): void {
    $request = Request::create(
        'https://bench.example/api/v1/proposals?limit=9',
    );
    $request->setSession($session);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('Lecture API en échec.');
    }
};
$measure = static function (int $count, Closure $operation): array {
    $times = [];
    for ($i = 0; $i < $count; ++$i) {
        $start = hrtime(true);
        $operation();
        $times[] = (hrtime(true) - $start) / 1e6;
    }
    sort($times);

    return [
        'samples' => $count,
        'medianMs' => round($times[(int) floor($count * 0.5)], 3),
        'p95Ms' => round($times[(int) ceil($count * 0.95) - 1], 3),
        'maxMs' => round(max($times), 3),
    ];
};
try {
    $call();
    $connection = $kernel->getContainer()->get('doctrine')->getConnection();
    $database = new DemoDatabase(
        $connection,
        dirname(__DIR__),
        new RelationalProjection($connection),
    );
    $engine = new SimulationEngine();
    $report = [
        'date' => gmdate('c'),
        'php' => PHP_VERSION,
        'scope' => 'Séquentiel local HttpKernel chaud ; 300 personnages évolutifs / 27 propositions / 2700 votes avec historique / SQLite. Exclut réseau HTTP, concurrence, PHP-FPM et télémétrie réelle.',
        'api' => $measure(100, $call),
    ];
    $workspace = $database->workspace('benchmark');
    $state = $workspace['state'];
    $revision = $workspace['revision'];
    $number = 0;
    $paris = array_values(
        array_filter(
            $state['citizens'],
            static fn (array $c): bool => $c['territoryId'] === 't1',
        ),
    );
    $report['voteAndTransactionalProjection'] = $measure(
        50,
        static function () use (
            &$state,
            &$revision,
            &$number,
            $paris,
            $engine,
            $database,
        ): void {
            $id = $paris[$number++]['id'];
            $previous = array_values(
                array_filter(
                    $state['votes'],
                    static fn (array $vote): bool => $vote['citizenId'] ===
                        $id && $vote['consultationId'] === 'c1',
                ),
            )[0];
            $state = $engine->apply(
                $state,
                'vote.cast',
                [
                    'citizenId' => $id,
                    'consultationId' => 'c1',
                    'choice' => $previous['choice'] === 'pour' ? 'contre' : 'pour',
                ],
                bin2hex(random_bytes(16)),
            );
            $database->save('benchmark', $revision++, $state);
        },
    );
    $report['peakMemoryBytes'] = memory_get_peak_usage(true);
    echo json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    ) . "\n";
} finally {
    $kernel->shutdown();
    foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
