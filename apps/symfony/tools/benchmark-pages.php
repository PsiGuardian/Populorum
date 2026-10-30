<?php

declare(strict_types=1);

/** Reproducible local GET measurements; disposable SQLite, no real users or network. */
require __DIR__ . '/bootstrap.php';

use App\Service\ActionForms;
use App\Service\PerformanceTrace;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$options = getopt('', ['output:', 'samples:', 'child:', 'database:', 'scale:', 'neighbors:', 'sections', 'writes', 'writes-only']);
$child = isset($options['child']);
$database = $child
    ? $options['database']
    : dirname(__DIR__) . '/var/perf-' . bin2hex(random_bytes(8)) . '.sqlite';
if (
    !is_string($database)
    || !preg_match('~/var/perf-[a-f0-9]{16}\.sqlite$~D', $database)
) {
    throw new InvalidArgumentException('Only disposable benchmark databases are allowed.');
}
if (!$child) {
    copy(dirname(__DIR__) . '/data/populorum-demo.sqlite', $database);
}
$_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///' . $database;
$_SERVER['POPULORUM_SCENARIO'] = '';
$_SERVER['POPULORUM_REQUIRE_LOGIN'] = '0';
$_SERVER['POPULORUM_TIMING'] = '1';
$kernel = new App\Kernel('demo', false);
$session = new Session(new MockArraySessionStorage());
$session->set('simulation_owner', 'performance-lab');
$call = static function (string $path) use (&$kernel, $session): array {
    $request = Request::create('https://bench.example' . $path);
    $request->setSession($session);
    $start = hrtime(true);
    $response = $kernel->handle($request);
    $handled = (hrtime(true) - $start) / 1e6;
    $phases = PerformanceTrace::report();
    $startTerminate = hrtime(true);
    $kernel->terminate($request, $response);
    $result = [
        'status' => $response->getStatusCode(),
        'handleMs' => $handled,
        'terminateMs' => (hrtime(true) - $startTerminate) / 1e6,
        'bytes' => strlen($response->getContent() ?: ''),
        'phases' => $phases,
    ];
    unset($response, $request);
    gc_collect_cycles();

    return $result;
};
if ($child) {
    $path = base64_decode($options['child'], true);
    if (
        !is_string($path)
        || !str_starts_with($path, '/')
        || str_contains($path, "\n")
    ) {
        throw new InvalidArgumentException('Invalid local route.');
    }
    $result = $call($path);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}
$stats = static function (array $values): array {
    sort($values);

    return [
        'samples' => count($values),
        'medianMs' => round($values[intdiv(count($values), 2)], 3),
        'p95Ms' => round($values[(int) ceil(count($values) * 0.95) - 1], 3),
        'minMs' => round(min($values), 3),
        'maxMs' => round(max($values), 3),
    ];
};
$pages = [
    'Accueil' => '/',
    'Territoires' => '/territoires',
    'Propositions' => '/chemins',
    'Consultations' => '/consultations',
    'Expériences' => '/experiences',
    'Convergence' => '/convergence',
    'Mémoire' => '/memoire',
    'Transparence' => '/transparence',
    'Infrastructure' => '/infrastructure',
    'Gouvernance' => '/gouvernance',
    'Participer' => '/participer',
    'Charte' => '/charte',
    'Récits' => '/recits',
    'Services et missions' => '/services-et-missions',
    'Budget' => '/budget-et-priorites',
    'Besoins et devis' => '/besoins-et-devis',
    'Proposition détaillée' => '/proposals/fiche/p1',
    'Consultation détaillée' => '/consultations/fiche/c1',
    'Expérience détaillée' => '/experiments/fiche/e1',
    'Personnage et raisonnement' => '/characters/u1',
    'Connexion' => '/citizen/login',
    'Vérification d’intégrité' => '/verify',
    'API état' => '/api/v1/state',
    'API propositions' => '/api/v1/proposals',
    'API résultats' => '/api/v1/results',
    'Export mémoire' => '/export/memory',
    'Actualisation courte (nouvelle)' => '/api/v1/live-results',
];
foreach (isset($options['sections']) ? [] : array_keys(ActionForms::TYPES) as $type) {
    $pages['Action : ' . $type] = '/action/' . $type;
}
if (isset($options['writes-only'])) {
    $pages = [];
}
$samples = min(30, max(3, (int) ($options['samples'] ?? 9)));
$report = [
    'at' => gmdate('c'),
    'php' => PHP_VERSION,
    'opcacheCli' => (bool) ini_get('opcache.enable_cli'),
    'samples' => $samples,
    'fixture' => '300 personnages, 27 propositions, 2700 positions avec historique ; dossiers et missions initialement vides',
    'scope' => 'Local séquentiel, HttpKernel demo/debug=0. Processus frais avec caches Symfony déjà compilés ; kernel réutilisé pour chaud. Aucun réseau, navigateur, concurrence ou PHP-FPM. Phases inclusives : ne pas les additionner.',
    'pages' => [],
];
try {
    $report['firstWorkspaceAndRequest'] = $call('/');
    $scale = min(5, max(1, (int) ($options['scale'] ?? 1)));
    $neighbors = min(4, max(0, (int) ($options['neighbors'] ?? 0)));
    if ($scale > 1 || $neighbors > 0) {
        require __DIR__ . '/scale-fixture.php';
        $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $database]);
        $db = new App\Service\DemoDatabase($connection, dirname(__DIR__), new App\Service\RelationalProjection($connection));
        if ($scale > 1) {
            $original = $db->workspace('performance-lab');
            $db->save('performance-lab', $original['revision'], performanceScaleFixture($original['state'], $scale));
        }
        for ($neighbor = 1; $neighbor <= $neighbors; ++$neighbor) {
            $db->workspace('neighbor-' . $neighbor);
        }
        $report['fixture'] = ($scale * 300) . ' citoyens, ' . ($scale * 27) . ' propositions, ' . ($scale * 2700) . ' positions ; ' . $neighbors . ' autres simulations. Données synthétiques disjointes, trois villes conservées.';
        $report['scale'] = $scale;
        $report['neighbors'] = $neighbors;
        $connection->close();
        unset($original, $db, $connection);
        gc_collect_cycles();
    }
    $report['databaseBytes'] = filesize($database);
    foreach ($pages as $label => $path) {
        $kernel->shutdown();
        $kernel = new App\Kernel('demo', false);
        gc_collect_cycles();
        $first = $call($path);
        $rows = [];
        for (
            $i = 0;
            $i <
            (str_starts_with($label, 'Action : ')
                ? min(3, $samples)
                : $samples);
            ++$i
        ) {
            $rows[] = $call($path);
        }
        $phases = [];
        foreach (
            array_unique(
                array_merge(
                    ...array_map(
                        static fn (array $row): array => array_keys(
                            $row['phases'],
                        ),
                        $rows,
                    ),
                ),
            ) as $name
        ) {
            $phases[$name] = $stats(
                array_map(
                    static fn (array $row): float => $row['phases'][$name][
                        'ms'
                    ] ?? 0.0,
                    $rows,
                ),
            );
        }
        echo $label . ': ' . $first['status'] . PHP_EOL;
        $report['pages'][] = [
            'label' => $label,
            'path' => $path,
            'status' => $first['status'],
            'bytes' => $first['bytes'],
            'firstHandleMs' => round($first['handleMs'], 3),
            'warm' => $stats(array_column($rows, 'handleMs')),
            'terminate' => $stats(array_column($rows, 'terminateMs')),
            'phases' => $phases,
        ];
        if (isset($options['output'])) {
            file_put_contents(
                $options['output'],
                json_encode(
                    $report,
                    JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_UNICODE |
                        JSON_THROW_ON_ERROR,
                ),
            );
        }
    }
    if (isset($options['writes'])) {
        $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $database]);
        $db = new App\Service\DemoDatabase($connection, dirname(__DIR__), new App\Service\RelationalProjection($connection));
        $report['writeSamples'] = [];
        for ($sample = 0; $sample < 3; ++$sample) {
            $start = hrtime(true);
            $workspace = $db->workspace('performance-lab');
            $readMs = (hrtime(true) - $start) / 1e6;
            $start = hrtime(true);
            $next = (new App\Service\SimulationEngine())->apply($workspace['state'], 'worker.availability', ['citizenId' => 'u1', 'available' => (bool) ($sample % 2), 'optIn' => true], bin2hex(random_bytes(16)));
            $applyMs = (hrtime(true) - $start) / 1e6;
            $start = hrtime(true);
            $db->save('performance-lab', $workspace['revision'], $next);
            $report['writeSamples'][] = ['readMs' => $readMs, 'applyMs' => $applyMs, 'saveMs' => (hrtime(true) - $start) / 1e6];
            unset($workspace, $next);
            gc_collect_cycles();
        }
        $report['projectionBytes'] = $connection->fetchAssociative('SELECT length(state) AS source, length(page_state) AS page, length(overview_state) AS overview FROM simulations WHERE id = ?', ['performance-lab']);
        $connection->close();
    }
    $report['peakMemoryBytes'] = memory_get_peak_usage(true);
    $json =
        json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";
    if (isset($options['output'])) {
        file_put_contents($options['output'], $json);
        echo count($report['pages']) . " routes measured.\n";
    } else {
        echo $json;
    }
} finally {
    $kernel->shutdown();
    foreach ([$database, $database . '-wal', $database . '-shm'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
