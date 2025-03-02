<?php

namespace Drutiny\DependencyInjection;

use Drutiny\Attribute\CompilerPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Lazy load services using uninstalled plugins.
 *
 * This allows plugins to be left uninstalled if unused.
 */
#[CompilerPass(type: PassConfig::TYPE_OPTIMIZE)]
class InstalledPluginPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $graph = $container->getCompiler()->getServiceReferenceGraph();

        foreach ($container->getParameter('plugin.registry') as $id) {
            $plugin = $container->get($id);
            if ($plugin->isInstalled()) {
                continue;
            }
            // Get the services that require this uninstalled plugin.
            $dependants = $graph->getNode($id)->getInEdges();
            foreach ($dependants as $edge) {
                // Load the definition of the dependant service to this uninstalled plugin.
                $definition = $container->getDefinition($edge->getSourceNode()->getId());
                // Don't load these classes because they won't be needed if the plugins are not installed.
                //$definition->setLazy(true);
            }
        }
    }
}
