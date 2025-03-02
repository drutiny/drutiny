<?php

namespace Drutiny\Audit\Exception;

use Drutiny\Audit\AuditInterface;

class AuditIrrelevantException extends \Exception
{

    public function getStatus()
    {
        return AuditInterface::IRRELEVANT;
    }
}
