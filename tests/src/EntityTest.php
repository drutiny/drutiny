<?php

namespace DrutinyTests;

use Drutiny\AuditResponse\AuditResponse;
use Drutiny\AuditResponse\State;
use Drutiny\Policy;
use Drutiny\PolicyFactory;
use Drutiny\Target\TargetFactory;

class EntityTest extends KernelTestCase
{

    protected $target;

    public function testPolicyObjectUsage()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Pass');
        $this->assertEquals($policy->name, 'Test:Pass');

        $this->assertInstanceOf(Policy::class, $policy);

      // Testing dynamic assignment to policy properties.
        $name_test = $policy->with(name: 'Test:Test');
        $this->assertEquals($name_test->name, 'Test:Test');

      // Testing dynamic assignment of parameters.
        $param_test = $policy->with(parameters: [
        'foo' => 'bar',
        'baz' => 'gat'
        ]);

        $this->assertEquals($param_test->parameters->get('foo'), 'bar');
        $this->assertEquals($param_test->parameters->all()['baz'], 'gat');

      // Confirm the export can be imported verbatim.
        $policy2 = new Policy(...$policy->export());
        $this->assertEquals($policy->title, $policy2->title);
    }

    public function testTargetObjectUsage()
    {
        $target = $this->container->get(TargetFactory::class)->create('none:none');
        $target->setUri('bar');
        $this->assertEquals($target->getProperty('uri'), 'bar');
    }

    public function testAuditResponse()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Pass');
        $response = new AuditResponse(
            policy: $policy,
            state: State::SUCCESS,
            tokens: [
            'foo' => 'bar',
            ],
            timestamp: 0,
            timing: 0
        );
        $this->assertArrayHasKey('foo', $response->tokens);
        $this->assertContains('bar', $response->tokens);
        $this->assertTrue($response->state->isSuccessful());
    }
}
