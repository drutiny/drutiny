<?php

namespace Drutiny\Target\Exception;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire: false)]
class TargetSourceFailureException extends \Exception
{
    const ERROR_CODE = 219;

    public function __construct(string $message, \Throwable|null $previous = null)
    {
        parent::__construct(message: $message, code: self::ERROR_CODE, previous:$previous);
    }
}
