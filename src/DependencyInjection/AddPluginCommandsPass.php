<?php

namespace Drutiny\DependencyInjection;

use Drutiny\Attribute\CompilerPass;
use Drutiny\Console\Application;
use Drutiny\Console\Command\PluginCollectionCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[CompilerPass(type: PassConfig::TYPE_OPTIMIZE)]
class AddPluginCommandsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getParameter('plugin.collections') as $id => $class_name) {
            $listCommandDefinition = new Definition(Command::class);
            $listCommandDefinition->setFactory([PluginCollectionCommand::class, 'getListCommand']);
            $listCommandDefinition->setArgument('pluginCollection', new Reference($id));
            $container->setDefinition($listCommandDefinitionId = 'command.list.'.$id, $listCommandDefinition);

            $addCommandDefinition = new Definition(Command::class);
            $addCommandDefinition->setFactory([PluginCollectionCommand::class, 'getAddCommand']);
            $addCommandDefinition->setArgument('pluginCollection', new Reference($id));
            $container->setDefinition($addCommandDefinitionId = 'command.add.'.$id, $addCommandDefinition);

            $deleteCommandDefinition = new Definition(Command::class);
            $deleteCommandDefinition->setFactory([PluginCollectionCommand::class, 'getDeleteCommand']);
            $deleteCommandDefinition->setArgument('pluginCollection', new Reference($id));
            $container->setDefinition($deleteCommandDefinitionId = 'command.delete.'.$id, $deleteCommandDefinition);
            
            $container->getDefinition(Application::class)
                      ->addMethodCall('add', [new Reference($listCommandDefinitionId)])
                      ->addMethodCall('add', [new Reference($addCommandDefinitionId)])
                      ->addMethodCall('add', [new Reference($deleteCommandDefinitionId)]);
        }
    }
}
