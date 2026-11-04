<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Service\EssentialServices;
use App\Service\ServiceContinuityPlans;
use App\Service\WaterContinuityGraph;

assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);

$catalog = EssentialServices::CATALOG;
if (count($catalog) !== 28) {
    throw new RuntimeException('Le référentiel doit conserver ses 28 services.');
}
foreach ($catalog as $id => $service) {
    ServiceContinuityPlans::for($id);
    foreach ($service['depends'] as $dependency) {
        if (!isset($catalog[$dependency])) {
            throw new RuntimeException("Dépendance inconnue : $id → $dependency");
        }
    }
}
$scenarios = ServiceContinuityPlans::for('water')['scenarios'];
if (count($scenarios) !== 6) {
    throw new RuntimeException('Six scénarios Eau sont attendus.');
}
$ids = array_column($scenarios, 'id');
if (count(array_unique($ids)) !== 6) {
    throw new RuntimeException('Identifiants de scénarios dupliqués.');
}
foreach ($ids as $id) {
    $graph = WaterContinuityGraph::forScenario($id);
    $nodes = array_column($graph['nodes'], null, 'id');
    foreach ($graph['edges'] as $edge) {
        if (!isset($nodes[$edge['from']], $nodes[$edge['to']])) {
            throw new RuntimeException('Arête pointant vers un nœud inexistant.');
        }
    }
    if (!in_array(true, array_column($graph['nodes'], 'affected'), true)) {
        throw new RuntimeException('Aucun nœud associé au scénario ' . $id);
    }
}
echo "Référentiel, six scénarios et graphe Eau : OK\n";
