<?php

namespace Drutiny\Attribute;

use Attribute;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
#[Autoconfigure(autowire: false)]
class UseService
{
    public function __construct(
        public readonly string  $id,
        public readonly string $method
    ) {
    }

    /**
     * Inject a container service into another service object.
     */
    public function inject(object $service, ContainerInterface $container)
    {
        call_user_func([$service, $this->method], $container->get($this->id));
        return $service;
    }
}
