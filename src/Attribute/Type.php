<?php

namespace Drutiny\Attribute;

use InvalidArgumentException;

enum Type: string
{
    case INTEGER = 'int';
    case STRING = 'string';
    case BOOLEAN = 'boolean';
    case ARRAY = 'array';
    case HASH = 'hash';
    case FLOAT = 'float';
    case NULL = 'null';

    /**
     * Get the Type from PHP gettype().
     */
    public static function fromVariable(mixed $var):static
    {
        if (is_array($var)) {
            return array_is_list($var) ? static::ARRAY : static::HASH;
        }
        return match (gettype($var)) {
            'boolean' => static::BOOLEAN,
            'integer' => static::INTEGER,
            'double' => static::FLOAT,
            'string' => static::STRING,
            'object' => static::HASH,
            'NULL' => static::NULL,
            default => throw new InvalidArgumentException("Unsupported variable type: " . gettype($var))
        };
    }

    /**
     * Determine if the passed value is of the same Type.
     */
    public function is(mixed $var):bool
    {
        // Empty hashes otherwise return as arrays.
        if (is_array($var) && empty($var) && $this == static::HASH) {
            return true;
        }
        return $this == static::fromVariable($var);
    }
}
