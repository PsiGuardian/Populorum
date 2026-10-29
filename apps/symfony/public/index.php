<?php
declare(strict_types=1);
require dirname(__DIR__).'/tools/bootstrap.php';
$kernel=new App\Kernel($_SERVER['APP_ENV']??'demo',filter_var($_SERVER['APP_DEBUG']??false,FILTER_VALIDATE_BOOLEAN));
$request=Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
