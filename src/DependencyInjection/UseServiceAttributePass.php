<?php

namespace Drutiny\DependencyInjection;

use Drutiny\Attribute\UseService;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class UseServiceAttributePass implements CompilerPassInterface
{
    /**
     * Decorate services with the UseService attribute.
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $service_id => $definition) {
            if (!$class = $definition->getClass()) {
                continue;
            }
            if (!class_exists($class, false)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            $attributes = [];
            do {
                $attributes = array_merge($attributes, $reflection->getAttributes(UseService::class));
                $reflection = $reflection->getParentClass();
            } while ($reflection);

            if (empty($attributes)) {
                continue;
            }

            do {
                // Recreate the attribute as a service instance for this class.
                $use_service = array_pop($attributes)->newInstance();
                $use_service_id = "$service_id.{$use_service->method}.{$use_service->id}";
                $use_service_definition = new Definition(UseService::class);
                $use_service_definition->setArguments([
                    '$id' => $use_service->id,
                    '$method' => $use_service->method,
                ]);
                $container->setDefinition($use_service_id, $use_service_definition);

                // Decorate this class with the useService injector.
                $decorator = clone $definition;
                $decorator->setDecoratedService($service_id);
                $decorator->setFactory([new Reference($use_service_id), 'inject']);
                $decorator->setArguments([new Reference('.inner'), new Reference('service_container')]);
                $container->setDefinition("$use_service_id.decorator", $decorator);
            } while (count($attributes));
        }
    }
}
