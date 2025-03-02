<?php

namespace Drutiny\DependencyInjection;

use Drutiny\Audit\TwigEvaluator;
use Drutiny\Audit\TwigEvaluatorObject;
use Drutiny\AuditFactory;
use Drutiny\Settings;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * Store collections of service IDs tagged by a common tag.
 */
class TwigEvaluatorPass implements CompilerPassInterface
{
    const FILENAME = 'dependencies.drutiny.yml';

    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        $twigEvaluator = $container->getDefinition(TwigEvaluator::class);
        foreach ($this->getRegistry($container) as $ns => $functions) {
            $id = 'twigEvaluator.'.$ns;
            $definition = new Definition(TwigEvaluatorObject::class);
            $definition->setArgument('$namespace', $ns);
            $definition->setArgument('$set', $functions);
            $definition->setArgument('$twigEvaluator', new Reference(TwigEvaluator::class));
            $definition->setArgument('$auditFactory', new Reference(AuditFactory::class));
            $definition->setPublic($container->hasParameter('phpunit.testing'));
            $container->setDefinition($id, $definition);

            $twigEvaluator->addMethodCall('setContext', [
                $ns,
                new Reference($id)
            ]);
        }
    }

    /**
     * Get a cached registry because searching the filesystem costs.
     *
     * This is public so the expression:reference command can access this registry.
     */
    public function getRegistry(ContainerInterface|Settings $container): array
    {
        // At compile time, we'll get the ContainerInterface while later on we should recieve a Settings
        // instance if called again.
        $dirs = ($container instanceof ContainerInterface) ? $container->getParameter('extension.dirs') : $container->get('extension.dirs');
        $dirs = array_filter($dirs, function ($dir) {
            return file_exists($dir."/".self::FILENAME);
        });
        $registry = [];
        foreach ($dirs as $dir) {
            $registry = array_merge($registry, Yaml::parseFile($dir."/".self::FILENAME));
        }
        return $registry;
    }
}
