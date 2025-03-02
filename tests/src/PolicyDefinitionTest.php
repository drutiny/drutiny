<?php


namespace DrutinyTests;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Policy;
use Drutiny\PolicyFactory;
use Drutiny\PolicySource\LocalFs;
use Drutiny\Profile\PolicyDefinition;

class PolicyDefinitionTest extends KernelTestCase
{
    protected PolicyFactory $policyFactory;
    protected LocalFs $policySource;

    protected function setup(): void
    {
        parent::setup();
        $this->policyFactory = $this->container->get(PolicyFactory::class);
        $this->policySource = $this->container->get(LocalFs::class);
    }

    public function testPolicyDefinitionBuilding()
    {
        $orig_policy =$this->policyFactory->loadPolicyByName('Test:Pass', $this->policySource);
        assert($orig_policy instanceof Policy);

        $definition = new PolicyDefinition(name: 'Test:Pass');
        $def_policy = $definition->getPolicy($this->container->get(PolicyFactory::class));

        $this->assertInstanceOf(Policy::class, $def_policy);
        $this->assertEquals($orig_policy->name, $def_policy->name);
        $this->assertEquals($orig_policy, $def_policy);
        $this->assertEquals($orig_policy->severity, $def_policy->severity);
        $this->assertEquals($orig_policy->parameters, $def_policy->parameters);
        $this->assertEquals($orig_policy->build_parameters, $def_policy->build_parameters);

        $definition = new PolicyDefinition(
            name: 'Test:Pass',
            severity: 'high',
            parameters: ['bar' => 'foo'],
            build_parameters: ['limit' => 22]
        );
        $def_policy = $definition->getPolicy($this->container->get(PolicyFactory::class));

        $this->assertInstanceOf(Policy::class, $def_policy);
        $this->assertEquals($orig_policy->name, $def_policy->name);
        $this->assertTrue($def_policy->parameters->has('bar'));
        $this->assertTrue($def_policy->build_parameters->has('limit'));
        $this->assertNotEquals($orig_policy, $def_policy);
        $this->assertNotEquals($orig_policy->severity, $def_policy->severity);
        $this->assertNotEquals($orig_policy->parameters, $def_policy->parameters);
        $this->assertNotEquals($orig_policy->build_parameters, $def_policy->build_parameters);
    }

    public function testDefinitionFromPolicy()
    {
        $orig_policy =$this->policyFactory->loadPolicyByName('Test:Pass', $this->policySource);
        assert($orig_policy instanceof Policy);

        $definition = $orig_policy->getDefinition();
        $this->assertInstanceOf(PolicyDefinition::class, $definition);
        $this->assertEquals($orig_policy->name, $definition->name);
        $this->assertEquals($orig_policy->severity, $definition->severity);
        $this->assertEquals($orig_policy->weight, $definition->weight);
        $this->assertEquals($orig_policy->parameters, $definition->parameters);
        $this->assertEquals($orig_policy->build_parameters, $definition->build_parameters);

        $def_policy = $definition->getPolicy($this->container->get(PolicyFactory::class));

        $this->assertInstanceOf(Policy::class, $def_policy);
        $this->assertEquals($orig_policy->name, $def_policy->name);
        $this->assertEquals($orig_policy, $def_policy);
        $this->assertEquals($orig_policy->severity, $def_policy->severity);
        $this->assertEquals($orig_policy->parameters, $def_policy->parameters);
        $this->assertEquals($orig_policy->build_parameters, $def_policy->build_parameters);
    }
}
