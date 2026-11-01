<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Service/BudgetEvidenceCatalog.php';

$projectDir = dirname(__DIR__);
$evidence = new App\Service\BudgetEvidenceCatalog($projectDir);
$all = $evidence->all();
$errors = [];
if (count($all) < 25) {
    $errors[] = 'Le catalogue contient moins de 25 sources.';
}
if (!isset($all['ofgl-consolide'], $all['paris-subventions'], $all['marseille-cfu'], $all['lehavre-cfu'])) {
    $errors[] = 'Une référence de contrôle est absente.';
}
$marseilleIds = array_column($evidence->forScope('13055'), 'id');
if (!in_array('marseille-cfu', $marseilleIds, true) || in_array('paris-subventions', $marseilleIds, true)) {
    $errors[] = 'La sélection Marseille inclut des documents municipaux de Paris.';
}
$franceIds = array_column($evidence->forScope('FR'), 'id');
if (count($franceIds) !== count($all)) {
    $errors[] = 'Le référentiel France ne réunit pas toutes les références.';
}
foreach ($all as $source) {
    if (!str_starts_with($source['url'], 'https://') || empty($source['limitations'])) {
        $errors[] = 'Référence incorrecte : ' . $source['id'];
    }
}
if ($errors) {
    fwrite(STDERR, "ÉCHEC :\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
printf("OK : %d sources ; catégories : %d ; sélection Paris/Marseille séparée.\n", count($all), count($evidence->grouped('FR')));
echo "Catalogue documentaire uniquement ; aucun transfert ni montant généré par cet import.\n";
