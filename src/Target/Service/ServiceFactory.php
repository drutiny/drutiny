<?php

namespace Drutiny\Target\Service;

use Drutiny\Settings;
use Drutiny\Target\Transport\TransportInterface;
use Exception;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class ServiceFactory
{
    public readonly array $serviceMap;
    public function __construct(
        Settings $settings,
        protected ContainerInterface $container
    ) {
        $this->serviceMap = $settings->get('service.registry');
    }

    public function get($id, TransportInterface $transport):ServiceInterface
    {
        isset($this->serviceMap[$id]) ?: throw new Exception("No such service exists: $id");

        $registry = [TransportInterface::class => $transport];

        $reflection = new ReflectionClass($this->serviceMap[$id]);

        $args = [];
        $construct = $reflection->getMethod('__construct');
        foreach ($construct->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!$parameter->hasType()) {
                throw new Exception("{$id} constructor parameter '$name' has no type-hinting.");
            }
            $type = $parameter->getType();
            // Use the provided TransportInterface object when required.
            // Use the container for all other types.
            $args[$name] = $registry[(string) $type] ?? $this->container->get((string) $type);
        }
        return $reflection->newInstance(...$args);
    }
}
