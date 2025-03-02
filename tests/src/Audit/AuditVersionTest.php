<?php

namespace DrutinyTests\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\AuditFactory;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\AuditResponse\State;
use Drutiny\Policy;
use Drutiny\Policy\Compatibility\IncompatibleVersionException;
use Drutiny\Policy\Severity;
use Drutiny\PolicyFactory;
use Drutiny\Target\TargetFactory;
use Drutiny\Target\TargetInterface;
use DrutinyTests\KernelTestCase;
use InvalidArgumentException;

class AuditVersionTest extends KernelTestCase
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
            ->with(class: TestAudit::class);
    }

    protected function audit(Policy $policy): AuditResponse
    {
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        return $audit->execute($policy);
    }

    public function testPolicyVersionTooOld()
    {
        $policy = $this->getPolicyStub()
            ->with(audit_build_info: [
                TestAudit::class . ':1.0'
            ]);
        $response = $this->audit($policy);
        $this->assertEquals(State::ERROR, $response->state);
        $this->assertArrayHasKey('exception', $response->tokens);
        $this->assertArrayHasKey('exception_type', $response->tokens);
        $this->assertEquals(IncompatibleVersionException::class, $response->tokens['exception_type']);
    }

    public function testPolicyVersionTooNew()
    {
        $policy = $this->getPolicyStub()
            ->with(audit_build_info: [
                TestAudit::class . ':100.0'
            ]);
        $response = $this->audit($policy);
        $this->assertEquals(State::ERROR, $response->state);
        $this->assertArrayHasKey('exception', $response->tokens);
        $this->assertArrayHasKey('exception_type', $response->tokens);
        $this->assertEquals(IncompatibleVersionException::class, $response->tokens['exception_type']);

        $policy = $this->getPolicyStub()
            ->with(audit_build_info: [
                TestAudit::class . ':10.200'
            ]);
        $response = $this->audit($policy);
        $this->assertEquals(State::ERROR, $response->state);
    }

    public function testPolicyUsingCorrectVersion()
    {
        $policy = $this->getPolicyStub()
            ->with(audit_build_info: [
                TestAudit::class . ':10.4'
            ]);
        $response = $this->audit($policy);
        $this->assertEquals(State::SUCCESS, $response->state);
    }

    public function testPolicyUsingCompatibleVersion()
    {
        $policy = $this->getPolicyStub()
            ->with(audit_build_info: [
                TestAudit::class . ':9.9'
            ]);
        $response = $this->audit($policy);
        $this->assertEquals(State::SUCCESS, $response->state);

        $policy = $this->getPolicyStub()
            ->with(audit_build_info: [
                TestAudit::class . ':10.1'
            ]);
        $response = $this->audit($policy);
        $this->assertEquals(State::SUCCESS, $response->state);
    }

    public function testPolicyMultiAuditInfo()
    {
        $policy = $this->getPolicyStub()
            ->with(audit_build_info: [
                TestAudit::class . ':9.9',
                TestAudit2::class . ':4.2'
            ]);
        $response = $this->audit($policy);
        $this->assertEquals(State::SUCCESS, $response->state);
    }

    public function testPolicyMultiAuditInfoOneFails()
    {
        $policy = $this->getPolicyStub()
            ->with(audit_build_info: [
                TestAudit::class . ':9.9',
                TestAudit2::class . ':4.9'
            ]);
        $response = $this->audit($policy);
        $this->assertEquals(State::ERROR, $response->state);
    }
}
