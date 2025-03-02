<?php

namespace Drutiny\DependencyInjection;

use Drutiny\Attribute\Plugin;
use Drutiny\Attribute\UsePlugin;
use Drutiny\Config\Config;
use Drutiny\Plugin\PluginCollection;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Load plugins for classes from PHP attributes on the class.
 */
class PluginArgumentsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $registry = [];
        $useServices = [];
        $pluginCollections = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if (strpos($id, '.abstract.instanceof.') !== false) {
                continue;
            }
            if (!$class = $definition->getClass()) {
                continue;
            }
            if (!class_exists($class, false)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Plugin::class);
            $useAttributes = $reflection->getAttributes(UsePlugin::class);
            if (!empty($useAttributes)) {
                $useServices[$id] = $useAttributes;
            }
            if (empty($attributes)) {
                continue;
            }
            $pluginAttribute = $attributes[0]->newInstance();
            $pluginAttribute->buildFieldAttributes($class);

            $pluginServiceId = 'plugin'.$pluginAttribute->name;

            $pluginCollections[$pluginServiceId] = $pluginAttribute->collectionKey === null ? null : $class;

            // Create a config service for the namespace.
            $configDefinition = new Definition(Config::class);
            $configDefinition->setFactory([new Reference('config'), 'load']);
            $configDefinition->setArgument('$namespace', $pluginAttribute->name);
            $container->setDefinition($configServiceId = 'config.'.$pluginAttribute->name, $configDefinition);

            // Create a credential service for the namespace.
            $credDefinition = new Definition(Config::class);
            $credDefinition->setFactory([new Reference('credentials'), 'load']);
            $credDefinition->setArgument('$namespace', $pluginAttribute->name);
            $container->setDefinition($credentialServiceId = 'credentials.'.$pluginAttribute->name, $credDefinition);

            // Create a state service for the namespace.
            $stateDefinition = new Definition(Config::class);
            $stateDefinition->setFactory([new Reference('state'), 'load']);
            $stateDefinition->setArgument('$namespace', $pluginAttribute->name);
            $container->setDefinition($stateServiceId = 'state.'.$pluginAttribute->name, $stateDefinition);

            // Create a service representation of the plugin attribute
            $pluginAttributeDefinition = new Definition(Plugin::class);
            $pluginAttributeDefinition->setFactory([Plugin::class, 'fromClass']);
            $pluginAttributeDefinition->setArgument('$class_name', $class);
            $container->setDefinition($pluginAttributeServiceId = 'attribute.' . $pluginAttribute->name, $pluginAttributeDefinition);
            
            // Create a new service matching the plugin requirements.
            $serviceDefinition = $pluginAttribute->collectionKey === null ? clone $container->getDefinition($pluginAttribute->class) : clone $container->getDefinition(PluginCollection::class);
            $serviceDefinition->setArgument('$pluginConfig', new Reference($configServiceId));
            $serviceDefinition->setArgument('$pluginCredentials', new Reference($credentialServiceId));
            $serviceDefinition->setArgument('$pluginState', new Reference($stateServiceId));
            $serviceDefinition->setArgument('$attribute', new Reference($pluginAttributeServiceId));
            // Explicitly using pluginServiceId to be clear we're using the attribute name as an ID.
            $container->setDefinition($pluginServiceId, $serviceDefinition);

            $registry[$pluginAttribute->name] = $pluginServiceId;

            // Inject the new service as the passed in argument for the consuming service.
            $definition->setArgument($pluginAttribute->as, new Reference($pluginServiceId));
        }
        $container->setParameter('plugin.registry', $registry);
        $container->setParameter('plugin.collections', array_filter($pluginCollections));

        // UsePlugin attribute provides a named plugin to a service defined in another service.
        foreach ($useServices as $id => $attributes) {
            $definition = $container->getDefinition($id);
            foreach ($attributes as $reflection) {
                $usePlugin = $reflection->newInstance();
                if (!isset($registry[$usePlugin->name])) {
                    throw new ServiceNotFoundException($usePlugin->name, $id, null, array_keys($registry));
                }
                $definition->setArgument($usePlugin->as, new Reference($registry[$usePlugin->name]));
            }
        }
    }
}
