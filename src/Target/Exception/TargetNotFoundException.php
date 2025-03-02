<?php

namespace Drutiny\Target\Exception;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Used when a target has a valid identifier but cannot be found.
 *
 * Use InvalidTargetException when the target identifier is invalid.
 * Use TargetLoadingException when the target is found but encounters errors when loading it.
 */
#[Autoconfigure(autowire: false)]
class TargetNotFoundException extends \Exception
{
    const ERROR_CODE = 220;

    public function __construct(string $message, \Throwable|null $previous = null)
    {
        parent::__construct(message: $message, code: self::ERROR_CODE, previous:$previous);
    }
}
