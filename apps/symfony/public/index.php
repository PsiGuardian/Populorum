<?php

declare(strict_types=1);
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Populorum : dependances Composer absentes.\n";
    echo "Depuis apps/symfony : composer install --no-interaction --prefer-dist --no-progress\n";
    exit;
}
require dirname(__DIR__) . '/tools/bootstrap.php';
$kernel = new App\Kernel(
    $_SERVER['APP_ENV'] ?? 'demo',
    filter_var($_SERVER['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
);
$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
