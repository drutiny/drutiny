<?php

namespace DrutinyTests;

use Drutiny\Console\Application;
use Drutiny\Kernel;
use Drutiny\Policy;
use Drutiny\Sandbox\Sandbox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ExpressionLanguageTest extends KernelTestCase {

  protected $target;

  protected function setUp(): void
  {
      parent::setUp();
      $this->target = $this->container->get('target.factory')->create('none:none');
      $this->target->setUri('https://example.com/');
  }

  public function testPolicyObjectUsage()
  {
      $language = $this->container->get('expression_language');
      $this->assertInstanceOf(ExpressionLanguage::class, $language);

      $this->assertEquals($language->evaluate('policy("Test:Pass")'), 'success');

      $params = [
        'version' => '1.2.4',
        'target' => $this->target
      ];
      $this->assertEquals($language->evaluate('semver_gt("1.2.3", version)', $params), false);
      $this->assertEquals($language->evaluate('semver_gt("4.2.3", version)', $params), true);

      $this->assertEquals($language->evaluate('semver_gte("1.2.3", version)', $params), false);
      $this->assertEquals($language->evaluate('semver_gte("4.2.3", version)', $params), true);
      $this->assertEquals($language->evaluate('semver_gte("1.2", "1.2")'), true);
      $this->assertEquals($language->evaluate('semver_gte("1.2", "1.2.0")'), false);
  }
}
