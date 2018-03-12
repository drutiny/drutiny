<?php

namespace Drutiny\Sandbox;

use Drutiny\Target\Target;
use Drutiny\AuditInterface;
use Drutiny\Check\RemediableInterface;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Policy;
use Drutiny\Cache;

/**
 * Run check in an isolated environment.
 */
class Sandbox {
  use DrushDriverTrait;
  use ExecTrait;
  use ParameterTrait;
  use LoggerTrait;


  /**
   * @var \Drutiny\Target\Target
   */
  protected $target;

  /**
   * @var \Drutiny\Audit
   */
  protected $audit;

  /**
   * @var \Drutiny\Policy
   */
  protected $policy;

  /**
   * Create a new Sandbox.
   *
   * @param string $target
   *   The class name of the target to create.
   *
   * @param Drutiny\CheckInformation $check
   *   The class name of the target to create.
   */
  public function __construct($target, Policy $policy) {
    $object = new $target($this);
    if (!$object instanceof Target) {
      throw new \InvalidArgumentException("$target is not a valid class for Target.");
    }
    $this->target = $object;

    $class = $policy->get('class');
    $object = new $class($this);
    if (!$object instanceof AuditInterface) {
      throw new \InvalidArgumentException("$class is not a valid Audit class.");
    }
    $this->audit = $object;
    $this->policy = $policy;
  }

  /**
   * Run the check and capture the outcomes.
   */
  public function run() {
    $response = new AuditResponse($this->getPolicy());

    try {
      $outcome = $this->getAuditor()->execute($this);
      $response->set($outcome, $this->getParameterTokens());
    }
    catch (\Exception $e) {
      $this->setParameter('exception', $e->getMessage());
      $response->set(AuditResponse::ERROR, $this->getParameterTokens());
    }

    return $response;
  }

  /**
   * Remediate the check if available.
   */
  public function remediate() {
    $response = new AuditResponse($this->getPolicy());
    try {

      // Do not attempt remediation on checks that don't support it.
      if (!($this->getAuditor() instanceof RemediableInterface)) {
        throw new \Exception(get_class($this->getAuditor()) . ' is not remediable.');
      }

      // Make sure remediation does report false positives due to caching.
      Cache::purge();
      $outcome = $this->getAuditor()->remediate($this);
      $response->set($outcome, $this->getParameterTokens());
      if ($response->isSuccessful()) {
        $response->set(AuditResponse::REMEDIATED, $this->getParameterTokens());
      }
    }
    catch (\Exception $e) {
      $this->setParameter('exception', $e->getMessage());
      $response->set(AuditResponse::ERROR, $this->getParameterTokens());
    }

    return $response;
  }

  /**
   *
   */
  public function getAuditor() {
    return $this->audit;
  }

  /**
   *
   */
  public function getPolicy() {
    return $this->policy;
  }

  /**
   *
   */
  public function getTarget() {
    return $this->target;
  }

  /**
   * A wrapper function for traits to use.
   */
  public function sandbox()
  {
    return $this;
  }
}
