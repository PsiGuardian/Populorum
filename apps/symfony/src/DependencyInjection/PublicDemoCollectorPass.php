<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PublicDemoCollectorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('data_collector.request')) {
            $container
                ->getDefinition('data_collector.request')
                ->setClass(\App\DataCollector\PublicDemoRequestCollector::class);
        }
        foreach (['data_collector.form', 'data_collector.doctrine', 'data_collector.exception'] as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->clearTag('data_collector');
            }
        }
    }
}
