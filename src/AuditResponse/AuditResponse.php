<?php

namespace Drutiny\AuditResponse;

use DateTime;
use Drutiny\Policy;
use Drutiny\Attribute\ArrayType;
use Drutiny\Entity\ExportableInterface;
use Drutiny\Policy\PolicyType;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Class AuditResponse.
 *
 * @package Drutiny\AuditResponse
 */
#[Autoconfigure(autowire: false)  ]
class AuditResponse implements ExportableInterface
{
    #[ArrayType('keyed')]
    public readonly array $tokens;
    public function __construct(
        public readonly Policy $policy,
        public readonly State $state,
        array $tokens = [],
        public readonly ?int $timestamp = null,
        public readonly ?int $timing = null
    ) {
      // Ensure the state passed matches a compatible state with the policy type.
        if ($state != $state->withPolicyType($policy->type)) {
            throw new AuditResponseException("Cannot accept state {$state->name} for policy of type 'data': {$policy->name}");
        }

        $tokens['chart'] = $policy->chart;
        $this->tokens = $tokens;
    }

    /**
     * Get the AudiResponse Policy.
     * @deprecated use policy attribute.
     */
    public function getPolicy():Policy
    {
        return $this->policy;
    }

    /**
     * @deprecated use tokens attribute.
     */
    public function getTokens():array
    {
        return $this->tokens;
    }

    /**
     * Get the exception message if present.
     */
    public function getExceptionMessage():string
    {
        return isset($this->tokens['exception']) ? $this->tokens['exception'] : '';
    }

    /**
     * Get the type of response based on policy type and audit response.
     */
    public function getType():string
    {
        $type = str_replace('_', '-', strtolower($this->state->name));
        return match ($type) {
            'warning-fail' => 'failure',
            default => $type
        };
    }

    /**
     * @deprecated use state property.
     */
    public function isSuccessful():bool
    {
        return $this->state->isSuccessful();
    }

    /**
     * @deprecated use state property.
     */
    public function isFailure():bool
    {
        return $this->state->isFailure();
    }

    /**
     * @deprecated use state property.
     */
    public function isNotice():bool
    {
        return $this->state->isNotice();
    }

    /**
     * @deprecated use state property.
     */
    public function hasWarning():bool
    {
        return $this->state->hasWarning();
    }

    /**
     * @deprecated use state property.
     */
    public function hasError():bool
    {
        return $this->state->hasError();
    }

    /**
     * @deprecated use state property.
     */
    public function isNotApplicable():bool
    {
        return $this->state->isNotApplicable();
    }

    /**
     * @deprecated use state property.
     */
    public function isIrrelevant():bool
    {
        return $this->state->isIrrelevant();
    }

    public function getSeverity():string
    {
        return $this->policy->severity->value;
    }

    public function getSeverityCode():int
    {
        return $this->policy->severity->getWeight();
    }

    /**
     * {@inheritdoc}
     */
    public function export():array
    {
        return [
        'policy' => $this->policy->name,
        'status' => $this->state->isSuccessful(),
        'is_notice' => $this->state->isNotice(),
        'has_warning' => $this->state->hasWarning(),
        'has_error' => $this->state->hasError(),
        'is_not_applicable' => $this->state->isNotApplicable(),
        'type' => $this->getType(),
        'severity' => $this->getSeverity(),
        'severity_code' => $this->getSeverityCode(),
        'exception' => $this->getExceptionMessage(),
        'tokens' => $this->tokens,
        'state' => $this->state,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function import($export)
    {

      // $this->state = $export['state'];
      // $this->tokens = $export['tokens'];
        $this->policy = drutiny()->get('policy.factory')->loadPolicyByName($export['policy']);
        unset($export['policy']);
        $this->importUnserialized($export);
    }
}
