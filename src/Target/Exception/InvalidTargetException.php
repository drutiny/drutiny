<?php

namespace Drutiny\Target\Exception;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Use when a target identifier is invalid.
 *
 * This exception should be thrown before a target is attempted to be loaded.
 * Use TargetLoadingException if a problem occurs while loading the target instead.
 */
#[Autoconfigure(autowire: false)]
class InvalidTargetException extends \Exception
{
    const ERROR_CODE = 222;

    public function __construct(string $message, \Throwable|null $previous = null)
    {
        parent::__construct(message: $message, code: self::ERROR_CODE, previous:$previous);
    }
}
