<?php

namespace Drutiny\Audit;

enum DynamicParameterType: string
{
    case REPLACE = '^';
    case EVALUATE = '$';
    case STATIC = '!';
    case NONE = '';

    /**
     * Determine the enum value from a parameter name.
     */
    public static function fromParameterName(string $name): static
    {
        return match (substr($name, 0, 1)) {
            static::REPLACE->value => static::REPLACE,
            static::EVALUATE->value => static::EVALUATE,
            static::STATIC->value => static::STATIC,
            default => static::NONE
        };
    }

    /**
     * Remove the token from the front of a parameeter name.
     */
    public function stripParameterName(string $name): string
    {
        return (strlen($this->value) > 0 && strpos($name, $this->value) === 0) ? substr($name, 1) : $name;
    }

    /**
     * Add the token to the front of a parameter name.
     */
    public function decorateParameterName(string $name): string
    {
        return $this->value . $name;
    }

    /**
     * Get the title of the dynamic parameter type.
     */
    public function getTitle(): string
    {
        return match ($this) {
            static::REPLACE => 'Token replacement',
            static::EVALUATE => 'Twig runtime evaluation',
            static::STATIC => 'Explicit bypass',
            static::NONE => 'none',
        };
    }
}
