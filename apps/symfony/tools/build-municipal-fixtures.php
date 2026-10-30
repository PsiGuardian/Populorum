<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$directory = dirname(__DIR__) . '/data';
$state = new App\Service\MunicipalFixtureBuilder()->build($directory);
file_put_contents(
    $directory . '/scenario.json',
    json_encode(
        $state,
        JSON_PRETTY_PRINT |
            JSON_PRESERVE_ZERO_FRACTION |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR,
    ) . "\n",
);
file_put_contents(
    $directory . '/scenario.json.gz',
    gzencode(file_get_contents($directory . '/scenario.json'), 9),
);
$archive = gzopen($directory . '/citizen-deliberations.ndjson.gz', 'wb9');
if (!$archive) {
    throw new RuntimeException('Création de l’archive des raisonnements impossible.');
}
$simulation = new App\Service\MentalSimulation();
try {
    foreach ($state['votes'] as $vote) {
        $decision = $simulation->dossier($state, $vote);
        gzwrite(
            $archive,
            json_encode(
                $decision,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_THROW_ON_ERROR,
            ) . "\n",
        );
    }
} finally {
    gzclose($archive);
}
echo "Fixture reproductible : 300 profils évolutifs, 27 propositions, 2 700 votes avec douze réponses, trois tours conservés.\n";
