<?php
declare(strict_types=1);
namespace App\DependencyInjection;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
final class PublicDemoCollectorPass implements CompilerPassInterface{
 public function process(ContainerBuilder $c):void{
  if($c->hasDefinition('data_collector.request'))$c->getDefinition('data_collector.request')->setClass(\App\DataCollector\PublicDemoRequestCollector::class);
  foreach(['data_collector.form','data_collector.doctrine','data_collector.exception'] as $id)if($c->hasDefinition($id))$c->getDefinition($id)->clearTag('data_collector');
 }
}
