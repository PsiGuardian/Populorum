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
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR,
    ) . "\n",
);
echo "Fixture reproductible : 300 personnages fictifs, 27 propositions, 6 consultations, 210 votes fictifs, 3 protocoles.\n";
