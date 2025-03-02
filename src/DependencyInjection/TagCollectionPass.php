<?php

namespace Drutiny\DependencyInjection;

use Drutiny\Attribute\KeyableAttributeInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Store collections of service IDs tagged by a common tag.
 */
class TagCollectionPass implements CompilerPassInterface
{

    /**
     * @param string $tag The tag to pull from the service container.
     * @param string $parameter The name of the parameter to store the tag registry.
     */
    public function __construct(protected string $tag, protected string $parameter, protected $keyableInterface = KeyableAttributeInterface::class)
    {
    }
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        $registry = [];
        foreach (array_keys($container->findTaggedServiceIds($this->tag)) as $id) {
            $key = null;
            $reflection = new ReflectionClass($container->getDefinition($id)->getClass());
            //$reflection = $container->getReflectionClass($id);
            $attributes = $reflection->getAttributes($this->keyableInterface, ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($attributes)) {
                $key = $attributes[0]->newInstance()->getKey();
            }
            if (isset($key)) {
                $registry[$key] = $id;
            } else {
                $registry[] = $id;
            }
        }
        $container->setParameter($this->parameter, $registry);
    }
}
