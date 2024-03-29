<?php

namespace Drutiny\Target\Exception;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Use when a target was found but encounterd errors when loading.
 */
#[Autoconfigure(autowire: false)]
class TargetLoadingException extends \Exception
{
    const ERROR_CODE = 221;

    public function __construct(string $message, \Throwable|null $previous = null)
    {
        parent::__construct(message: $message, code: self::ERROR_CODE, previous:$previous);
    }
}
