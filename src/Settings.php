<?php

namespace Drutiny;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Settings
{
    protected ParameterBagInterface $parameterBag;

    public function __construct(Container $container)
    {
        $this->parameterBag = $container->getParameterBag();
    }

    /**
     * Get a single settings.
     *
     * Returns null if the parameter does not exist.
     */
    public function get($id)
    {
        if ($this->parameterBag->has($id)) {
            return $this->parameterBag->get($id);
        }
    }

    /**
     * Get all settings.
     */
    public function getAll():array
    {
        return $this->parameterBag->all();
    }

    /**
     * Has a settings.
     */
    public function has($id)
    {
        return $this->parameterBag->has($id);
    }
}
