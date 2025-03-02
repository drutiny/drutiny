<?php

namespace Drutiny\Attribute;

use Attribute;
use Drutiny\Plugin as DrutinyPlugin;
use InvalidArgumentException;
use ReflectionClass;

#[Attribute]
class Plugin implements KeyableAttributeInterface
{
    protected PluginFieldCollection $fields;
    public function __construct(
        public readonly string $name,
        public readonly bool $hidden = false,
        public readonly string $class = DrutinyPlugin::class,
        public readonly string $as = '$plugin',
        public readonly ?string $collectionKey = null
    ) {
        $this->fields = new PluginFieldCollection;
    }

    public function getKey():string
    {
        return $this->name;
    }

    /**
     * Get an array of field attributes keyed by field name.
     */
    public function buildFieldAttributes(string $class_name):self
    {
        $reflection = new ReflectionClass($class_name);
        foreach ($reflection->getAttributes(PluginField::class) as $attribute) {
            $this->fields->add($attribute->newInstance());
        }
        return $this;
    }

    public function getFieldAttributes():array
    {
        return $this->fields->getAll();
    }

    public function hasField(string $name): bool
    {
        return array_key_exists($name, $this->getFieldAttributes());
    }

    public function getField(string $name):PluginField
    {
        return $this->fields->get($name);
    }

    /**
     * Build a Plugin instance from a class with a Plugin attribute declared.
     */
    public static function fromClass(string $class_name):Plugin
    {
        if (!class_exists($class_name)) {
            throw new InvalidArgumentException("$class_name is not a valid class that exists.");
        }
        $reflection = new ReflectionClass($class_name);
        $attributes = $reflection->getAttributes(Plugin::class);
        $pluginInstance = $attributes[0]->newInstance();
        $pluginInstance->buildFieldAttributes($class_name);
        return $pluginInstance;
    }
}
