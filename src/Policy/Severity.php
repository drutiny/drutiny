<?php

namespace Drutiny\Policy;

use Exception;

enum Severity: string
{
    case NONE = 'none';
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    /**
     * Get the default case if none preset.
     */
    public static function getDefault():self
    {
        return Severity::NORMAL;
    }

    /**
     * Get the severity weight.
     */
    public function getWeight():int
    {
        return match ($this) {
            self::NONE => 0,
            self::LOW => 1,
            self::NORMAL => 2,
            self::HIGH => 4,
            self::CRITICAL => 8,
        };
    }

    /**
     * Check if a string is a severity case.
     */
    public static function has(string $value):bool
    {
        return in_array($value, array_map(fn($e) => $e->value, Severity::cases()));
    }

    /**
     * Get severity case from int or string value.
     */
    public static function fromValue(string|int|null $value): self
    {
        return match (gettype($value)) {
            'string' => self::from($value),
            'integer' => self::fromInt($value),
            default => self::getDefault(),
        };
    }

    /**
     * Return an Enum severity by its weight.
     */
    public static function fromInt(int $int):self
    {
        return match ($int) {
            1 => self::LOW,
            2 => self::NORMAL,
            4 => self::HIGH,
            8 => self::CRITICAL,
            default => throw new Exception("Unknown severity int code: $int.")
        };
    }
}
