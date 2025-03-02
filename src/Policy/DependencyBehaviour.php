<?php

namespace Drutiny\Policy;

use Drutiny\Audit\AuditInterface;
use Exception;

enum DependencyBehaviour: int
{
    case PASS = 0;
    case FAIL = 1;
    case OMIT = 4;
    case ERROR = 3;
    case REPORT_ONLY = 2;

    /**
     * Get the audit outcome from the enum value.
     */
    public function getAuditOutcome():int
    {
        return match ($this) {
            DependencyBehaviour::FAIL => AuditInterface::FAILURE,
            DependencyBehaviour::OMIT => AuditInterface::IRRELEVANT,
            DependencyBehaviour::ERROR => AuditInterface::ERROR,
            DependencyBehaviour::REPORT_ONLY => AuditInterface::NOT_APPLICABLE,
            default => AuditInterface::FAILURE
        };
    }

    /**
     * That the higher of two DependencyBehaviour enums.
     */
    public function higher(DependencyBehaviour $enum):DependencyBehaviour
    {
        return $this->value > $enum->value ? $this : $enum;
    }

    /**
     * Get an ENUM from a string value.
     */
    public static function get(string $type):self
    {
        return match ($type) {
            'fail' => DependencyBehaviour::FAIL,
            'omit' => DependencyBehaviour::OMIT,
            'error' => DependencyBehaviour::ERROR,
            'report_only' => DependencyBehaviour::REPORT_ONLY,
            default => throw new Exception("No such behaviour: $type. Supported values are fail, omit, error or report_only."),
        };
    }

    public function label():string
    {
        return match ($this) {
            DependencyBehaviour::FAIL => 'fail',
            DependencyBehaviour::OMIT => 'omit',
            DependencyBehaviour::ERROR => 'error',
            DependencyBehaviour::REPORT_ONLY => 'report_only',
            default => throw new Exception("Unknown label for DependencyBehaviour.")
        };
    }
}
