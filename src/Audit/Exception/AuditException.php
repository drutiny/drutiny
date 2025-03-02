<?php

namespace Drutiny\Audit\Exception;

use Drutiny\AuditResponse\State;
use Throwable;

class AuditException extends \Exception
{

    public function __construct(string $message, public readonly State $state = State::ERROR, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getStatus():int
    {
        return $this->state->value;
    }
}
