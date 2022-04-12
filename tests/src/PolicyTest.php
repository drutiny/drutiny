<?php

namespace DrutinyTests;

use Drutiny\Console\Application;
use Drutiny\Kernel;
use Drutiny\Policy;
use Drutiny\Sandbox\Sandbox;
use PHPUnit\Framework\TestCase;

class PolicyTest extends KernelTestCase {

  protected $target;

  protected function setUp(): void
  {
      parent::setUp();
      $this->target = $this->container->get('target.factory')->create('none:none');
  }

  public function testPass()
  {
    $policy = $this->container->get('policy.factory')->loadPolicyByName('Test:Pass');
    $audit = $this->container->get($policy->class);
    $response = $audit->execute($policy);
    $this->assertTrue($response->isSuccessful());
  }

  public function testFail()
  {
    $policy = $this->container->get('policy.factory')->loadPolicyByName('Test:Fail');
    $audit = $this->container->get($policy->class);
    $response = $audit->execute($policy);
    $this->assertFalse($response->isSuccessful());
  }

  public function testError()
  {
    $policy = $this->container->get('policy.factory')->loadPolicyByName('Test:Error');
    $audit = $this->container->get($policy->class);
    $response = $audit->execute($policy);
    $this->assertFalse($response->isSuccessful());
    $this->assertTrue($response->hasError());
  }

  public function testWarning()
  {
    $policy = $this->container->get('policy.factory')->loadPolicyByName('Test:Warning');
    $audit = $this->container->get($policy->class);
    $response = $audit->execute($policy);
    $this->assertTrue($response->isSuccessful());
    $this->assertTrue($response->hasWarning());
  }

  public function testNotApplicable()
  {
    $policy = $this->container->get('policy.factory')->loadPolicyByName('Test:NA');
    $audit = $this->container->get($policy->class);
    $response = $audit->execute($policy);
    $this->assertFalse($response->isSuccessful());
    $this->assertTrue($response->isNotApplicable());
  }

  public function testNotice()
  {
    $policy = $this->container->get('policy.factory')->loadPolicyByName('Test:Notice');
    $audit = $this->container->get($policy->class);
    $response = $audit->execute($policy);
    $this->assertTrue($response->isSuccessful());
    $this->assertTrue($response->isNotice());
  }

}
