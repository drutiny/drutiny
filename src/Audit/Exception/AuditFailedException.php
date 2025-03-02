<?php

namespace Drutiny\Audit\Exception;

use Drutiny\Audit\AuditInterface;

class AuditFailedException extends \Exception
{

    public function getStatus()
    {
        return AuditInterface::FAIL;
    }
}
