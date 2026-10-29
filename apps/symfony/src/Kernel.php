<?php
declare(strict_types=1);
namespace App;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
final class Kernel extends BaseKernel {use MicroKernelTrait;
 protected function build(\Symfony\Component\DependencyInjection\ContainerBuilder $container):void{parent::build($container);$container->addCompilerPass(new \App\DependencyInjection\PublicDemoCollectorPass());}
public function getProjectDir():string{return dirname(__DIR__);}}
