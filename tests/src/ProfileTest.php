<?php

namespace DrutinyTests;

// use Drutiny\Console\Application;
// use Drutiny\Kernel;
use Drutiny\Profile;
use PHPUnit\Framework\TestCase;

class ProfileTest extends KernelTestCase {

  protected $target;


  public function testUsage()
  {
    $profile = new Profile();
    $profile->name = 'test_profile';
    $profile->title = 'Test Profile';
    $profile->uuid = 'unique uuid';
    $profile->description = 'Adding a required description field.';
    $profile->addPolicies(['Test:Pass'])
            ->addPolicies([
              'Test:Fail' => [
                'severity' => 'high',
                'weight' => 10
              ]
            ])
            ->build();
    $policy_definitions = $profile->getAllPolicyDefinitions();
    $this->assertEquals(count($policy_definitions), 2);

    $this->assertArrayHasKey('Test:Fail', $policy_definitions);

    $this->assertEquals($policy_definitions['Test:Fail']->weight, 10);
    // $policy = $this->container->get('policy.factory')->loadPolicyByName('Test:Pass');
    // $audit = $this->container->get($policy->class);
    // $response = $audit->execute($policy);
    // $this->assertTrue($response->isSuccessful());
  }

  public function testIncludes()
  {
    $profile = new Profile();
    $profile->name = 'test_profile';
    $profile->title = 'Test Profile';
    $profile->uuid = 'unique uuid';
    $profile->description = 'Adding a required description field.';
    $profile->addPolicies(['Test:Pass'])
            ->addPolicies([
              'Test:Fail' => [
                'severity' => 'high',
                'weight' => 10
              ]
            ]);

    $include = new Profile();
    $include->name = 'include_profile';
    $include->title = 'Include Profile';
    $include->uuid = 'include uuid';
    $include->addPolicies(['Test:Warning', 'Test:Pass']);

    $profile->addInclude($include)->build();

    $policy_definitions = $profile->getAllPolicyDefinitions();
    $this->assertEquals(count($policy_definitions), 3);

    $this->assertArrayHasKey('Test:Warning', $policy_definitions);

    $this->assertEquals($policy_definitions['Test:Warning']->weight, 0);

    $this->assertEquals(2, array_search('Test:Fail', array_keys($policy_definitions)));
  }

}
