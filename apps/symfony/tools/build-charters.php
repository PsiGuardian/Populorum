<?php

declare(strict_types=1);

// Les sources lisibles restent à la racine du dépôt ; seule l'archive est déployée.
$application = dirname(__DIR__);
$repository = dirname($application, 2);
$documents = [];
foreach (glob($repository . '/docs/translations/*/CHARTER.md') as $file) {
    $documents[basename(dirname($file))] = file_get_contents($file);
}
$documents['fr_FR'] = file_get_contents($repository . '/CHARTER.md');
ksort($documents);
if (count($documents) !== 28 || in_array(false, $documents, true)) {
    throw new RuntimeException('Les 28 chartes sources sont requises.');
}
$json = json_encode(
    $documents,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
);
$archive = gzencode($json, 9);
if (
    $archive === false
    || file_put_contents($application . '/data/charters.json.gz', $archive) ===
        false
) {
    throw new RuntimeException('Impossible de produire l’archive des chartes.');
}
echo "28 chartes embarquées dans data/charters.json.gz\n";
