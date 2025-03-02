<?php

namespace DrutinyTests;

use Drutiny\AuditFactory;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\AuditResponse\State;
use Drutiny\PolicyFactory;
use Drutiny\Target\TargetFactory;

class AuditResponseTest extends KernelTestCase
{

    protected $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->target = $this->container->get(TargetFactory::class)->create('none:none');
    }

    public function testAuditExecute()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Pass');
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        $response = $audit->execute($policy);
        $this->assertInstanceOf(AuditResponse::class, $response, "Audit::execute returns AuditResponse object.");
        $this->assertInstanceOf(State::class, $response->state, "State is enum object");
        $this->assertTrue($response->state->isSuccessful(), "AuditReponse state indicates audit was successful.");
        $this->assertIsArray($response->tokens, "Can access tokens array.");
    }

    public function testSerializable()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Pass');
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        $response = $audit->execute($policy);

        $serial = serialize($response);
        $this->assertIsString($serial, "AuditResponse can be serialized");
        $this->assertInstanceOf(AuditResponse::class, unserialize($serial), "AuditResponse can be unserialized.");
    }
}
