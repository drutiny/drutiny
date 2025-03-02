<?php

namespace DrutinyTests;

use Drutiny\Policy;
use Drutiny\AuditFactory;
use Drutiny\Audit\TwigEvaluator;
use Drutiny\Target\TargetFactory;

/**
 * Test policy.depends field.
 */
class DependencyTest extends KernelTestCase
{

    public function testBase():void
    {
        $drupal = $this->container->get('twigEvaluator.Drupal');
        $this->assertIsObject($drupal);
    }

    public function testExpression():void
    {
        // Dependency factory loads the target from the twigEvaluator.
        $twigEvaluator = $this->container->get(TwigEvaluator::class);
        $targetFactory = $this->container->get(TargetFactory::class);
        $target = $targetFactory->mock('none');
        $target['drush.drupal-version'] = '8.2.1';
        $target['drush.bootstrap'] = true;
        $twigEvaluator->setContext('target', $target);

        $drupal = $this->container->get('twigEvaluator.Drupal');

        $this->assertTrue($drupal->versionSatisfies('^8.0'));
        $this->assertFalse($drupal->versionSatisfies('^7.4'));
        $this->assertTrue($drupal->versionSatisfies('~8'));
    }

    public function testAudit():void
    {
        $target = $this->loadMockTarget(
            'none',
            '/usr/local/bin/drush',
            ['syslog' => [
                'status' => 'enabled'
            ]]
        );
        $target['drush.bootstrap'] = 'Successful';
        
        $drupal = $this->container->get('twigEvaluator.Drupal');

        $this->assertTrue($drupal->moduleIsEnabled('syslog'));
    }

    public function testPolicy():void
    {
        $policy = $this->getPolicyWithDepends('Drupal.isBootstrapped');
        $target = $this->loadMockTarget();

        $policy = $this->getPolicyWithDepends('Drupal.isBootstrapped');
        $audit = $this->container->get(AuditFactory::class)->mock($policy->class, $target);
        $this->assertFalse($audit->execute($policy, $target)->isSuccessful(), "Dependency check failed the policy.");

        $target['drush.bootstrap'] = 'Successful';
        $this->assertTrue($audit->execute($policy, $target)->isSuccessful(), "Dependency check passes.");
    }

    protected function getPolicyWithDepends(...$expressions):Policy
    {
        $depends = [];
        foreach ($expressions as $expression) {
            $depends[] = [
                'syntax' => 'twig',
                'description' => 'Testing expressions',
                'expression' => $expression
            ];
        }
        // Set required fields.
        $policy = new Policy(...[
            'uuid' => __FUNCTION__,
            'name' => __FUNCTION__,
            'title' => __FUNCTION__,
            'description' => __FUNCTION__,
            'failure' => 'Failure message',
            'success' => 'Success message',
            'class' => 'Drutiny\Audit\AbstractAnalysis',
            'source' => 'phpunit',
            'parameters' => [
                'expression' => 'true'
            ],
            'depends' => $depends
        ]);
        return $policy;
    }
}
