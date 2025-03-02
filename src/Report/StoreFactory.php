<?php

namespace Drutiny\Report;

use Drutiny\Report\Store\StoreInterface;
use Drutiny\Settings;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

class StoreFactory
{
    public function __construct(protected Settings $settings, protected ContainerInterface $container)
    {
    }

    public function get(string $name): StoreInterface
    {
        return $this->container->get($this->settings->get('store.registry')[$name] ?? throw new InvalidArgumentException("'$name' is not a valid storage name."));
    }
}
