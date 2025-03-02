<?php

namespace Drutiny\Attribute;

use Attribute;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Attribute]
#[Autoconfigure(autowire:false)]
class AsSource extends Name
{
    public function __construct(
        public readonly string $name,
        public readonly int $weight = 0,
        public readonly bool $cacheable = true
    ) {
    }

    public static function fromClass(string $class_name):self
    {
        if (!class_exists($class_name)) {
            throw new InvalidArgumentException("$class_name is not a valid class that exists.");
        }
        $reflection = new ReflectionClass($class_name);
        return $reflection->getAttributes(AsSource::class)[0]->newInstance();
    }
}
