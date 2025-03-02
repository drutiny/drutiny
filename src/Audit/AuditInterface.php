<?php

namespace Drutiny\Audit;

use DateTimeInterface;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Policy;

/**
 * Audit interface.
 */
interface AuditInterface
{
  /**
   * The policy successfully passed the audit.
   */
    const SUCCESS = true;

  /**
   * Same as Audit::SUCCESS
   */
    const PASS = 1;

  /**
   * Same as Audit::FAILURE
   */
    const FAIL = false;

  /**
   * The policy failed to pass the audit.
   */
    const FAILURE = 0;

  /**
   * An audit returned non-assertive information.
   */
    const NOTICE = 2;

  /**
   * An audit returned success with a warning.
   */
    const WARNING = 4;

  /**
   * An audit returned failure with a warning.
   */
    const WARNING_FAIL = 8;

  /**
   * An audit did not complete and returned an error.
   */
    const ERROR = 16;

  /**
   * An audit was not applicable to the target.
   */
    const NOT_APPLICABLE = -1;

  /**
   * An audit that is irrelevant to the assessment and should be omitted.
   */
    const IRRELEVANT = -2;

    /**
     * @deprecated Use Attribute\Parameter::REQUIRED.
     */
    const PARAMETER_REQUIRED = 1;
    
    /**
     * @deprecated Use Attribute\Parameter::OPTIONAL.
     */
    const PARAMETER_OPTIONAL = 2;
    
    /**
     * @deprecated Use Attribute\Parameter::IS_ARRAY.
     */
    const PARAMETER_IS_ARRAY = 4;

    /**
     * Define how the audit class is to be
     */
    public function configure():void;

    /**
     * Audit the target against the policy and evaluate the result.
     */
    public function audit(Sandbox $sandbox);
    
    /**
     * Execute an audit against a given policy.
     *
     * @param Policy $policy
     * @param bool $remediate (@deprecated)
     *
     * @return AuditResponse
     *
     * @throws \Drutiny\Audit\AuditValidationException
     */
    public function execute(Policy $policy, $remediate = false): AuditResponse;

    /**
     * Report if the audit class is deprecated from use.
     */
    public function isDeprecated(): bool;

    public function getDefinition():InputDefinition;

    /**
     * @deprecated use getDefinition()->hasParameter($name).
     */
    public function hasArgument(string $name): bool;

    /**
     * Know if a parameter exists in the databag.
     *
     * To know if the parameter exists in the audit definition,
     * use Audit::getDefinition()->hasParameter($name).
     */
    public function hasParameter(string $name): bool;

    /**
     * Set a parameter. Typically provided by a policy.
     */
    public function setParameter(string $name, $value): AuditInterface;

    /**
     * Get a set parameter or provide the default value.
     */
    public function getParameter(string $name, $default_value = null);

    /**
     * Pass a policy to an audit class to prepare it for bulk auditing.
     *
     * When collections of policies use the same audit, some audit classes
     * may like to conduct data gathering in more effecient manners.
     * This prepare function is called for all policies that utilse the
     * same class.
     *
     * @return string|null a common identifier to batch policies on or null.
     */
    public function prepare(Policy $policy):?string;

    public function setReportingPeriod(DateTimeInterface $start, DateTimeInterface $end):self;
}
