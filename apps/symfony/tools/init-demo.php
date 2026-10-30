<?php

declare(strict_types=1);
$root = dirname(__DIR__);
$path = $root . '/.env.local';
if (is_file($path)) {
    echo "Configuration locale conservée.\n";
    exit(0);
}
$handle = fopen($path, 'x');
if (!$handle) {
    throw new RuntimeException('Création exclusive de .env.local impossible.');
}
chmod($path, 0600);
fwrite(
    $handle,
    'APP_SECRET=' .
        bin2hex(random_bytes(32)) .
        "\nGUARDIAN_ENCRYPTION_KEY=" .
        bin2hex(random_bytes(32)) .
        "\n",
);
fclose($handle);
echo "Clés locales générées dans .env.local (non versionné).\n";
