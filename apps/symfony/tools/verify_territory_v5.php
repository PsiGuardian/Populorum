<?php

declare(strict_types=1);

/** Run from apps/symfony: php tools/verify_territory_v5.php */
$project = dirname(__DIR__);
$autoload = $project . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "VENDOR MANQUANT : exécutez Composer dans apps/symfony.\n");
    exit(2);
}
require $autoload;
// Explicit local autoloader also works when vendor is symlinked in a disposable test copy.
spl_autoload_register(static function (string $class) use ($project): void {
    if (!str_starts_with($class, 'App\\')) return;
    $path = $project . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) require $path;
}, true, true);
// Test the project classes and Twig without booting a mutable Doctrine workspace.
$geo = new \App\Service\GeographyCatalog($project);
$scope = new \App\Service\TerritorySelection($geo);
$session = new \Symfony\Component\HttpFoundation\Session\Session(
    new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
);
$request = \Symfony\Component\HttpFoundation\Request::create('/chemins');
$request->setSession($session);
$fail = static function (string $message): never {throw new \RuntimeException($message);};
if ($scope->current($request, 'proposals') !== null) $fail('Le scope initial doit être France.');
$pilots = $geo->pilots();
$first = $pilots[0];
if (!$scope->choose($request, $first['code'])) $fail('Impossible de sélectionner la commune pilote.');
if (($scope->current($request, 'proposals')['code'] ?? '') !== $first['code']) $fail('Scope non mémorisé.');
if (!$scope->choose($request, 'FR') || $scope->current($request, 'proposals') !== null) $fail('Le retour France échoue.');
if ($scope->choose($request, '00000')) $fail('Code inexistant accepté.');
$legacy = \Symfony\Component\HttpFoundation\Request::create('/budget-et-priorites?city=' . $first['code']);
$legacy->setSession($session);
if (($scope->current($legacy, 'budget')['code'] ?? '') !== $first['code']) {
    $fail('Le lien budgétaire V3 ?city= doit continuer à sélectionner la commune.');
}
$scope->choose($request, 'FR');

$state = ['territories' => [['id'=>'t1','code'=>'75056'], ['id'=>'t2','code'=>'13055']],
 'proposals' => [['id'=>'p1','territoryId'=>'t1'], ['id'=>'p2','territoryId'=>'t2']],
 'consultations' => [['id'=>'c1','territoryId'=>'t1'], ['id'=>'c2','territoryId'=>'t2']],
 'votes' => [['consultationId'=>'c1'], ['consultationId'=>'c2']]];
$filtered = $scope->filterDemo($state, 't1');
if (count($filtered['territories']) !== 1 || count($filtered['proposals']) !== 1
    || count($filtered['consultations']) !== 1 || count($filtered['votes']) !== 1
    || count($state['proposals']) !== 2) $fail('Projection du scénario incorrecte.');
$twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader($project . '/templates'));
$twig->addFunction(new \Twig\TwigFunction('civic_path', static fn (): string => '/'));
$twig->addFunction(new \Twig\TwigFunction('csrf_token', static fn (): string => 'fixture'));
foreach (['form_start', 'form_end', 'form_widget', 'form_errors', 'form_row', 'form_label', 'form_rest', 'is_granted', 'path', 'url', 'asset', 'logout_path'] as $function) {
    $twig->addFunction(new \Twig\TwigFunction($function, static fn (): string => ''));
}
$templates = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project . '/templates'));
$n = 0;
foreach ($templates as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) continue;
    $name = substr($file->getPathname(), strlen($project . '/templates/'));
    $twig->parse($twig->tokenize(new \Twig\Source((string) file_get_contents($file->getPathname()), $name)));
    $n++;
}
$html = $twig->render('pages/no-local-simulation.html.twig', [
    'view' => 'home', 'labels' => ['home' => 'Accueil', 'territories' => 'Territoires'],
    'actor' => ['name' => 'Personnage fictif', 'preferences' => []],
    'selectedCommune' => ['code'=>'66134', 'name'=>'Passa'],
    'selectedPilotId' => null, 'geoRecent' => [], 'geoCoverage' => $geo->coverage(),
    'guardianPath' => '/demo-profiler-shield-populorum',
]);
if (!str_contains($html, 'Passa') || !str_contains($html, 'Pas de scénario local')) {
    $fail('Rendu du périmètre communal sans pilote incorrect.');
}
echo "OK : session France/commune, filtre pilote, isolation lecture, {$n} templates Twig analyses.\n";
echo 'Référentiel géographique : ' . json_encode($geo->coverage(), JSON_UNESCAPED_UNICODE) . "\n";
echo "Attention : ce test ne démarre ni Apache/PHP serveur ni Doctrine/SQLite.\n";
