<?php

namespace DrutinyTests;

use Drutiny\Console\Application;
use Drutiny\Kernel;
use Drutiny\Policy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;


class ApplicationTest extends KernelTestCase {

  public function testContainer()
  {
      $this->assertTrue(
        $this->application
        ->getKernel()
        ->getContainer()
        ->getParameter('phpunit.testing'));
  }

  public function testProfileRun()
  {
    $input = new ArrayInput([
      'command' => 'profile:run',
      'profile' => 'test',
      'target' => 'none:none'
    ]);

    $code = $this->application->run($input, $this->output);
    $this->assertIsInt($code);
    $this->assertEquals(0, $code);
    //$this->assertStringContainsString('Always pass test policy', $this->output->fetch());
  }

  public function testProfileList()
  {
    $input = new ArrayInput([
      'command' => 'profile:list',
    ]);

    $code = $this->application->run($input, $this->output);
    $this->assertIsInt($code);
    $this->assertEquals(0, $code);
    $this->assertStringContainsString('Test Profile', $this->output->fetch());
  }

  public function testProfileInfo()
  {
    $input = new ArrayInput([
      'command' => 'profile:info',
      'profile' => 'test'
    ]);

    $code = $this->application->run($input, $this->output);
    $this->assertStringContainsString('Test Profile', $this->output->fetch());
    $this->assertIsInt($code);
    $this->assertEquals(0, $code);

  }

  public function testPolicyList()
  {
    $input = new ArrayInput([
      'command' => 'policy:list'
    ]);

    $code = $this->application->run($input, $this->output);
    $this->assertStringContainsString('Always notice test policy', $this->output->fetch());
    $this->assertIsInt($code);
    $this->assertEquals(0, $code);

  }

  /**
   * @todo
   */
  public function testPolicyAudit()
  {
    $input = new ArrayInput([
      'command' => 'policy:audit',
      'policy' => 'Test:Pass',
      'target' => 'none:none'
    ]);

    $code = $this->application->run($input, $this->output);
    $this->assertIsInt($code);
    $this->assertEquals(0, $code);
    $this->assertStringContainsString('Always pass test policy', $this->output->fetch());
  }

  public function testPolicyInfo()
  {
    $input = new ArrayInput([
      'command' => 'policy:info',
      'policy' => 'Test:Pass'
    ]);

    $code = $this->application->run($input, $this->output);
    $this->assertIsInt($code);
    $this->assertEquals(0, $code);
    $this->assertStringContainsString('This policy should always pass', $this->output->fetch());
  }

  public function testAuditRun()
  {
    $input = new ArrayInput([
      'command' => 'audit:run',
      'audit' => 'Drutiny\Audit\AlwaysPass',
      'target' => 'none:none'
    ]);

    $code = $this->application->run($input, $this->output);
    $this->assertIsInt($code);
    $this->assertEquals(1, $code);
    $this->assertStringContainsString('Drutiny\Audit\AlwaysPass', $this->output->fetch());
  }
}
