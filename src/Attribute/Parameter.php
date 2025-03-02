<?php

namespace Drutiny\Attribute;

use Attribute;
use Drutiny\Audit\DynamicParameterType;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * @class An Audit Parameter definition.
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
#[Autoconfigure(autowire: false)]
class Parameter
{
    const REQUIRED = 1;
    const OPTIONAL = 2;

    public readonly string $class;

    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly int $mode = self::OPTIONAL,
        public readonly mixed $default = null,
        public readonly ?Type $type = null,
        public readonly ?array $enums = null,
        public readonly DynamicParameterType $preprocess = DynamicParameterType::NONE,
        string|object|null $class = null
    ) {
        if ($class !== null) {
            $this->fromClass($class);
        }
        if (in_array($name, ['parameters'])) {
            throw new InvalidArgumentException("Parameter name '$name' is a reserved keyword.");
        }
    }

    public function fromClass(string|object $class):static
    {
        if (is_object($class)) {
            $this->class = get_class($class);
        } elseif (!class_exists($class)) {
            throw new InvalidArgumentException($class . ' does not exist.');
        } else {
            $this->class = $class;
        }
        return $this;
    }

    public function isRequired():bool
    {
        return ($this->mode & static::REQUIRED) === static::REQUIRED;
    }

    public function validate(mixed $value)
    {
        if (!isset($this->type)) {
            return;
        }
        // Ignore null values. @see isRequired.
        if ($value === null) {
            return;
        }
        if (!$this->type->is($value)) {
            throw new InvalidArgumentException(sprintf("Value for parameter '{$this->name}' is type %s. Must be of type %s.", Type::fromVariable($value)->value, $this->type->value));
        }
        if (isset($this->enums) && !in_array($value, $this->enums)) {
            throw new InvalidArgumentException(sprintf("Invalid value passed to parameter '{$this->name}'. Allowed values: %s", implode(', ', $this->enums)));
        }
    }
}
