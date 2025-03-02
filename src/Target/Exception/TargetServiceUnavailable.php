<?php

namespace Drutiny\Target\Exception;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Use when a target cannot provide a requested service.
 */
#[Autoconfigure(autowire: false)]
class TargetServiceUnavailable extends \Exception
{
    const ERROR_CODE = 230;

    public function __construct(string $message, \Throwable|null $previous = null)
    {
        parent::__construct(message: $message, code: self::ERROR_CODE, previous:$previous);
    }
}
