<?php

namespace Drutiny;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PluginFactory
{
    protected ParameterBagInterface $plugins;
    public function __construct(protected ContainerInterface $container, Settings $settings)
    {
        $this->plugins = new FrozenParameterBag($settings->get('plugin.registry'));
    }

    /**
     * Get a plugin bu its name.
     */
    public function get(string $name):Plugin
    {
        return $this->container->get($this->plugins->get($name));
    }
}
