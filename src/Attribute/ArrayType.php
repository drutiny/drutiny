<?php

namespace Drutiny\Attribute;

use Attribute;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
#[Autoconfigure(autowire:false)]
class ArrayType
{
    public readonly string $type;

    public function __construct(string $type, public readonly ?string $of = null)
    {
        if (!in_array($type, ['keyed', 'indexed'])) {
            throw new InvalidArgumentException("'$type' not allowed. Must be 'keyed' or 'indexed'.");
        }
        $this->type = $type;
    }

    public function validate(array $value):bool
    {
        $correct_type = match ($this->type) {
            'keyed' => !array_is_list($value),
            'indexed' => array_is_list($value)
        };

        if (!$correct_type) {
            throw new InvalidArgumentException("Array is not {$this->type}.");
        }

        if (is_null($this->of)) {
            return true;
        }

        $valid_contents = array_filter($value, function ($item) {
            if (!class_exists($this->of) && !interface_exists($this->of)) {
                return gettype($item) == $this->of;
            }
            return $item instanceof $this->of;
        });

        if (count($valid_contents) != count($value)) {
            throw new InvalidArgumentException("Contents of array are not strictly of type {$this->of}.");
        }

        return true;
    }
}
