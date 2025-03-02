<?php

namespace Drutiny\AuditResponse;

use Drutiny\Policy\PolicyType;

enum State: int
{
    case SUCCESS = 1;
    case FAILURE = 0;
    case NOTICE = 2;
    case WARNING = 4;
    case WARNING_FAIL = 8;
    case ERROR = 16;
    case NOT_APPLICABLE = -1;
    case IRRELEVANT = -2;

    /**
     * Get the state from int or boolean values.
     */
    public static function fromValue(int|bool|string $value)
    {
        if (is_string($value)) {
            $value = is_numeric($value) ? (int) $value : (bool) $value;
        }
        return is_bool($value) ? ($value ? static::SUCCESS : static::FAILURE) : static::from($value);
    }

    /**
     * Special handling of state based on policy type.
     */
    public function withPolicyType(PolicyType $type):static
    {
        if ($type == PolicyType::AUDIT) {
            return $this;
        }
        // PolicyType::DATA
        return match ($this) {
            static::ERROR => static::ERROR,
            static::IRRELEVANT => static::IRRELEVANT,
            static::NOT_APPLICABLE => static::NOT_APPLICABLE,
            default => static::NOTICE,
        };
    }

    /**
     * Get the description of the AuditResponse state.
     */
    public function getDescription():string
    {
        return match ($this) {
            State::SUCCESS => 'The policy successfully passed the audit.',
            State::FAILURE => 'The policy failed to pass the audit.',
            State::NOTICE => 'The audit returned non-assertive information',
            State::NOT_APPLICABLE => 'The audit was not applicable to the target',
            State::WARNING => 'The audit returned success with a warning',
            State::WARNING_FAIL => 'The audit returned failure with a warning',
            State::ERROR => 'The audit did not complete and returned an error',
            State::IRRELEVANT => 'The audit that is irrelevant to the assessment and should be omitted',
        };
    }

    /**
     * Add a warning to an existing pass or fail state.
     */
    public function withWarning():static
    {
        if (!$this->isSuccessful() && !$this->isFailure()) {
            throw new AuditResponseException("Warnings can only be added to success and failure states. State not supported: " . $this->value);
        }
        return $this->isSuccessful() ? State::WARNING : State::WARNING_FAIL;
    }

    /**
     * State has a successful outcome including a warning.
     */
    public function isSuccessful():bool
    {
        return $this === State::SUCCESS || $this === State::SUCCESS || $this === State::NOTICE || $this === State::WARNING;
    }

    /**
     * State is in failure including warnings but excluding errors.
     */
    public function isFailure():bool
    {
        return !$this->isSuccessful() && !$this->isIrrelevant() && !$this->isNotApplicable() && !$this->hasError();
    }

    /**
     *
     */
    public function isNotice():bool
    {
        return $this === State::NOTICE;
    }

    /**
     * Has a warning if successful of not.
     */
    public function hasWarning():bool
    {
        return $this === State::WARNING || $this === State::WARNING_FAIL;
    }

    /**
     * State is in error.
     */
    public function hasError():bool
    {
        return $this === State::ERROR;
    }

    /**
     * State is not applicable.
     */
    public function isNotApplicable():bool
    {
        return $this === State::NOT_APPLICABLE;
    }

    /**
     * State is irrelevant.
     */
    public function isIrrelevant():bool
    {
        return $this === State::IRRELEVANT;
    }
}
