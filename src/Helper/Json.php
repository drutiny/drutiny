<?php

namespace Drutiny\Helper;

use Closure;
use DateTimeInterface;
use Drutiny\Target\TargetInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Json
{
    /**
     * Extract variables from known class types.
     */
    public static function extract(mixed $variable):mixed
    {
        return match (gettype($variable)) {
            'object' => match (true) {
                $variable instanceof ParameterBagInterface => self::extract($variable->all()),
                $variable instanceof TargetInterface => self::extract(array_combine(
                    $variable->getPropertyList(),
                    array_map(fn ($p) => $variable->getProperty($p), $variable->getPropertyList())
                )),
                $variable instanceof DateTimeInterface => $variable->format('c'),
                $variable instanceof Closure => null,
                default => self::extract(get_object_vars($variable))
            },
            'array' => array_map(fn ($a) => self::extract($a), $variable),
            default => $variable,
        };
    }
}
