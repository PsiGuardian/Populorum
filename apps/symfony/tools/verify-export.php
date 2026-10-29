<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
if (!isset($argv[1])) {
    fwrite(STDERR, "Usage : php tools/verify-export.php export.json\n");
    exit(2);
}
try {
    $s = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $r = new App\Service\SimulationEngine()->verify($s);
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    exit($r['valid'] ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, "Export invalide.\n");
    exit(1);
}
