<?php

namespace Drutiny\Target;

use Drutiny\Audit\TwigEvaluator;
use Drutiny\Settings;
use Drutiny\Target\Exception\InvalidTargetException;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TargetFactory
{
    protected array $targetMap;

    public function __construct(
        protected ContainerInterface $container,
        protected TwigEvaluator $twigEvaluator,
        Settings $settings
    ) {
        $this->targetMap = $settings->get('target.registry');
    }

    /**
     * Create a new TargetInterface instance from a target reference.
     */
    public function create(string $target_reference, ?string $uri = null):TargetInterface
    {
        // A file reference means to load the target from a dump file.
        if (file_exists($target_reference)) {
            return $this->fromExport(TargetExport::fromTemporaryFile($target_reference));
        }

        // By default, assume a target is using drush.
        $target_name = 'drush';
        $target_data = $target_reference;

        // If a colon is used, then an alternate target maybe used.
        if (strpos($target_reference, ':') !== false) {
            list($target_name, $target_data) = explode(':', $target_reference, 2);
        }

        $target = $this->container->get($this->targetMap[$target_name]
          ?? throw new InvalidArgumentException("$target_name is not a valid target tag. Valid tags are: " . implode(', ', array_keys($this->targetMap))));
        $target->setTargetName($target_reference);
        $target->load($target_data, $uri);

        // This makes the target inherintly accessible by the twigEvaluator
        // in other services.
        $this->twigEvaluator->setContext('target', $target);
        return $target;
    }

    public function mock($type):TargetInterface
    {
        $this->targetMap[$type] ?? throw new InvalidTargetException("No such target type '$type'.");
        return $this->container->get($this->targetMap[$type]);
    }

    public function export(TargetInterface $target)
    {
        return TargetExport::create($target);
    }

    /**
     * Create a target from an export of properties.
     */
    public function fromExport(TargetExport $export)
    {
        // By default, assume a target is using drush.
        $target_name = 'drush';

        // If a colon is used, then an alternate target maybe used.
        if (strpos($export->targetReference, ':') !== false) {
            list($target_name, ) = explode(':', $export->targetReference, 2);
        }

        $target = $this->container->get($this->targetMap[$target_name]
          ?? throw new InvalidArgumentException("$target_name is not a valid target tag. Valid tags are: " . implode(', ', array_keys($this->targetMap))));
        $target->setTargetName($export->targetReference);
        $target->loadByProperties($export->properties);

        // This makes the target inherintly accessible by the twigEvaluator
        // in other services.
        $this->twigEvaluator->setContext('target', $target);
        return $target;
    }

    /**
     * Display a list of target types and classes.
     */
    public function getTypes():array
    {
        return $this->targetMap;
    }

    /**
     * Test if a target is of a given type by its source name.
     */
    public function typeOf(TargetInterface $target, string $name = TargetInterface::class): bool
    {
        return $target instanceof ($this->targetMap[$name] ?? $name);
    }
}
