<?php

namespace DrutinyTests\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\AuditFactory;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Policy;
use Drutiny\Policy\Severity;
use Drutiny\PolicyFactory;
use Drutiny\Target\TargetFactory;
use Drutiny\Target\TargetInterface;
use DrutinyTests\KernelTestCase;

class AbstractAnalysisTest extends KernelTestCase
{
    protected TargetInterface $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->target = $this->container->get(TargetFactory::class)->create('none:none');
    }

    protected function getPolicyStub():Policy
    {
        return $this->container
            ->get(PolicyFactory::class)
            ->loadPolicyByName('Test:Pass')
            ->with(class: AbstractAnalysis::class);
    }

    protected function audit(Policy $policy): AuditResponse
    {
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        return $audit->execute($policy);
    }
  
    public function testPass()
    {
        $policy = $this->getPolicyStub();
        $response = $this->audit($policy);

        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isSuccessful());
    }

    public function testExpression()
    {
        $parameters = ['expression' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);

        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isSuccessful());

        $parameters['expression'] = 'false';
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);

        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());

        $parameters['expression'] = 'foo == bar';
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);

        $this->assertTrue($response->state->hasError());
    }

    public function testFailIf()
    {
        $parameters = ['failIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());

        $parameters['failIf'] = 'false';
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isSuccessful());

        $parameters = ['failIf' => 'true', 'expression' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());

        $parameters = ['failIf' => '1'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());

        $parameters = ['failIf' => '0'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isSuccessful());
    }

    public function testWarningIf()
    {
        $parameters = ['warningIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isSuccessful());
        $this->assertTrue($response->state->hasWarning());


        $parameters = ['warningIf' => 'false'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isSuccessful());
        $this->assertFalse($response->state->hasWarning());

        $parameters = ['warningIf' => 'false', 'failIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());
        $this->assertFalse($response->state->hasWarning());

        $parameters = ['warningIf' => 'true', 'failIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());
        $this->assertTrue($response->state->hasWarning());

        $parameters = ['warningIf' => 'true', 'expression' => 'PASS'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isSuccessful());
        $this->assertTrue($response->state->hasWarning());

        $parameters = ['warningIf' => 'true', 'not_applicable' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isNotApplicable());
        $this->assertFalse($response->state->hasWarning());
    }

    public function testNotApplicable()
    {
        $parameters = ['not_applicable' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isNotApplicable());
        $this->assertFalse($response->state->isSuccessful());
        $this->assertFalse($response->state->isFailure());
        $this->assertFalse($response->state->isNotice());
    }

    public function testSeverityCriticalIf()
    {
        $parameters = ['severityCriticalIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters, severity: Severity::NORMAL);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isSuccessful());
        $this->assertNotEquals(Severity::CRITICAL, $response->policy->severity, "Severity doesn't change when policy is successful");


        $parameters = ['severityCriticalIf' => 'true', 'failIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters, severity: Severity::NORMAL);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());
        $this->assertEquals(Severity::CRITICAL, $response->policy->severity, "Severity changes when policy is unsuccessful");
    }

    public function testSeverityHighIf()
    {
        $parameters = ['severityHighIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters, severity: Severity::NORMAL);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isSuccessful());
        $this->assertNotEquals(Severity::HIGH, $response->policy->severity, "Severity doesn't change when policy is successful");


        $parameters = ['severityHighIf' => 'true', 'failIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters, severity: Severity::NORMAL);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());
        $this->assertEquals(Severity::HIGH, $response->policy->severity, "Severity changes when policy is unsuccessful");

        $parameters = ['severityHighIf' => 'true', 'failIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters, severity: Severity::CRITICAL);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());
        $this->assertNotEquals(Severity::HIGH, $response->policy->severity, "Severity doesn't lower from high severity levels.");
    }

    public function testSeverityNormalIf()
    {
        $parameters = ['severityNormalIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters, severity: Severity::LOW);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isSuccessful());
        $this->assertNotEquals(Severity::NORMAL, $response->policy->severity, "Severity doesn't change when policy is successful");


        $parameters = ['severityNormalIf' => 'true', 'failIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters, severity: Severity::LOW);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());
        $this->assertEquals(Severity::NORMAL, $response->policy->severity, "Severity changes when policy is unsuccessful");

        $parameters = ['severityNormalIf' => 'true', 'failIf' => 'true'];
        $policy = $this->getPolicyStub()->with(parameters: $parameters, severity: Severity::CRITICAL);
        $response = $this->audit($policy);
        $this->assertFalse($response->state->hasError());
        $this->assertTrue($response->state->isFailure());
        $this->assertNotEquals(Severity::NORMAL, $response->policy->severity, "Severity doesn't lower from high severity levels.");
    }
}
