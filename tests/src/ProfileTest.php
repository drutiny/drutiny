<?php

namespace DrutinyTests;

// use Drutiny\Console\Application;
// use Drutiny\Kernel;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Policy;
use Drutiny\PolicyFactory;
use Drutiny\Profile;
use Drutiny\ProfileFactory;

class ProfileTest extends KernelTestCase
{

    protected $target;

    protected ProfileFactory $profileFactory;
    protected PolicyFactory $policyFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profileFactory = $this->container->get(ProfileFactory::class);
        $this->policyFactory = $this->container->get(PolicyFactory::class);
    }


    public function testUsage()
    {
        $profile = $this->profileFactory->create([
        'name' => 'test_profile',
        'source' => 'phpunit',
        'title' => 'Test profile',
        'uuid' => 'unique uuid',
        'description' => "Adding a required description field.",
        'policies' => [
        'Test:Pass' => [],
        'Test:Fail' => [
          'severity' => 'high',
          'weight' => 10
        ]
        ]
        ]);

        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertEquals(count($profile->policies), 2);
        $this->assertArrayHasKey('Test:Fail', $profile->policies);
        $this->assertEquals($profile->policies['Test:Fail']->weight, 10);
    }

    public function testManualPolicyDefinitions()
    {
        $policy = new Policy(...[
        'title' => 'Audit: ' . __FUNCTION__,
        'name' => '_test',
        'class' => AbstractAnalysis::class,
        'source' => __CLASS__.':'.__FUNCTION__,
        'description' => 'Verbatim run of an audit class',
        'remediation' => 'none',
        'success' => 'success',
        'failure' => 'failure',
        'warning' => 'warning',
        'uuid' => __FUNCTION__,
        'severity' => 'normal'
        ]);

        $profile = $this->profileFactory->create([
        'title' => 'Audit: ' . __FUNCTION__,
        'name' => 'audit_run',
        'uuid' => 'audit_run',
        'source' => __CLASS__.':'.__FUNCTION__,
        'description' => 'Wrapper profile for audit:run',
        'policies' => [
        $policy->name => $policy->getDefinition()
        ]
        ]);

        $this->assertArrayHasKey($policy->name, $profile->policies);
        $this->assertEquals($policy, $profile->policies[$policy->name]->getPolicy($this->policyFactory));
    }

    public function testIncludes()
    {
        $profile = $this->profileFactory->create([
        'name' => 'test_profile',
        'title' => 'Test profile',
        'uuid' => 'unique uuid',
        'source' => 'phpunit',
        'description' => "Adding a required description field.",
        'policies' => [
        'Test:Pass' => ['weight' => 15],
        'Test:Fail' => [
          'severity' => 'high',
          'weight' => 10
        ]
        ]
        ]);

        $include = $this->profileFactory->create([
        'name' => 'include_profile',
        'title' => 'Include profile',
        'uuid' => 'include uuid',
        'source' => 'phpunit',
        'description' => "Adding a required description field.",
        'policies' => [
        'Test:Warning' => [
          'weight' => -3
        ],
        'Test:Pass' => ['weight' => -2],
        ]
        ]);

        $merged_profile = $profile->mergeWith($include);

        $this->assertEquals(2, count($profile->policies));
        $this->assertEquals(2, count($include->policies));
        $this->assertEquals(3, count($merged_profile->policies), "Test:Pass policy does not appear twice.");
        $this->assertArrayHasKey('Test:Warning', $merged_profile->policies);
        $this->assertEquals($merged_profile->policies['Test:Warning']->weight, -3);
        $this->assertEquals(2, array_search('Test:Fail', array_keys($merged_profile->policies)));
        $this->assertEquals(0, array_search('Test:Warning', array_keys($merged_profile->policies)));
        $this->assertEquals(1, array_search('Test:Pass', array_keys($merged_profile->policies)));
    }

    public function testSerializable()
    {
        $profile = $this->profileFactory->create([
        'name' => 'test_profile',
        'title' => 'Test profile',
        'uuid' => 'unique uuid',
        'source' => 'phpunit',
        'description' => "Adding a required description field.",
        'policies' => [
        'Test:Pass' => ['weight' => 15],
        'Test:Fail' => [
          'severity' => 'high',
          'weight' => 10
        ]
        ]
        ]);
        $serial = serialize($profile);
        $this->assertIsString($serial, "Profile is serializable");
        $this->assertInstanceOf(Profile::class, $awaken_profile = unserialize($serial));
    }
}
