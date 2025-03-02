<?php

namespace Drutiny\Policy\Compatibility;

use Drutiny\Policy\AuditClass;
use Throwable;

class PolicyCompatibilityException extends \Exception
{
    public function __construct(
        public readonly AuditClass $constraint,
        string $message,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
