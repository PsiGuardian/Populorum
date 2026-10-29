<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    if (!class_exists(OmegaGuardian\ProfilerShield\Controller\GuardianController::class)) {
        return;
    }
    $path = OmegaGuardian\ProfilerShield\Util\Env::get('GUARDIAN_LOGIN_PATH');
    if (preg_match('~^/[a-zA-Z0-9/_-]{24,180}$~D', $path)) {
        $routes
            ->add('omega_guardian_entry', $path)
            ->controller('omega_guardian.controller::entry')
            ->methods(['GET', 'POST']);
    }
};
