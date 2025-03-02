<?php

namespace Drutiny\Audit\Exception;

use Drutiny\Audit\AuditInterface;

class AuditNotApplicableException extends \Exception
{

    public function getStatus()
    {
        return AuditInterface::NOT_APPLICABLE;
    }
}
