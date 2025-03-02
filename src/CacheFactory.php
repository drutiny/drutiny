<?php

namespace Drutiny;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class CacheFactory
{
    public readonly array $caches;
    public function __construct(protected ContainerInterface $container, Settings $settings)
    {
        $this->caches = $settings->get('cache.registry');
    }

    /**
     * Get a plugin bu its name.
     */
    public function get(string $name):AdapterInterface
    {
        if (in_array($name, $this->caches)) {
            return $this->container->get($name);
        }
        throw new InvalidArgumentException("No such caching service: $name. Available: " . implode(', ', $this->caches));
    }

    /**
     * @return Symfony\Contracts\Cache\CacheInterface[]
     */
    public function all(): array
    {
        return array_combine(
            $this->caches,
            array_map(fn ($id) => $this->container->get($id), $this->caches)
        );
    }

    public function clearAll(): void
    {
        foreach ($this->caches as $id) {
            $this->get($id)->clear();
        }
    }
}
