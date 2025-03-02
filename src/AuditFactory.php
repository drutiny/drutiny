<?php

namespace Drutiny;

use Drutiny\Attribute\UseService;
use Drutiny\Audit\AuditInterface;
use Drutiny\Audit\AuditValidationException;
use Drutiny\Audit\Exception\AuditException;
use Drutiny\Target\TargetFactory;
use Drutiny\Target\TargetInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class AuditFactory
{

    public function __construct(
        protected ContainerInterface $container,
        protected TargetFactory $targetFactory
    ) {
    }

    /**
     * Get an Audit object for the provided policy and target.
     */
    public function get(Policy $policy, TargetInterface $target):AuditInterface
    {
        $reflection = new ReflectionClass($policy->class);
        if (!$reflection->implementsInterface(AuditInterface::class)) {
            throw new AuditException("{$policy->class} does not implement " . AuditInterface::class);
        }
        return $this->mock($policy->class, $target);
    }

    /**
     * Get a mock audit instance without policy or target context.
     */
    public function mock(string $audit_class, ?TargetInterface $target = null):AuditInterface
    {
        $reflection = new ReflectionClass($audit_class);
        if (!$reflection->implementsInterface(AuditInterface::class)) {
            throw new AuditException("$audit_class does not implement " . AuditInterface::class);
        }
        $target = $target ?? $this->targetFactory->create('none:none');
        $registry = [
            TargetInterface::class => $target,
            $target::class => $target
        ];

        $args = [];
        $construct = $reflection->getMethod('__construct');
        foreach ($construct->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!$parameter->hasType()) {
                throw new AuditValidationException("{$audit_class} constructor parameter '$name' has no type-hinting.");
            }
            $type = $parameter->getType();
            // Ues the provided TargetInterface object when required.
            // Use the container for all other types.
            $args[$name] = $registry[(string) $type] ?? $this->container->get((string) $type);
        }
        $audit = $reflection->newInstance(...$args);

        $attributes = [];
        do {
            $attributes = array_merge($attributes, $reflection->getAttributes(UseService::class));
            $reflection = $reflection->getParentClass();
        } while ($reflection);

        if (empty($attributes)) {
            return $audit;
        }

        do {
            $service = array_pop($attributes)->newInstance();
            $service->inject($audit, $this->container);
        } while (count($attributes));

        return $audit;
    }
}
