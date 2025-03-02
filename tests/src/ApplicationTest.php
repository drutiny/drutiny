<?php

namespace DrutinyTests;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Yaml\Yaml;

class ApplicationTest extends KernelTestCase
{

  /**
   * Assert the phpunit container has loaded.
   */
    public function testContainer()
    {
        $this->assertTrue($this->container->getParameter('phpunit.testing'));
        $this->assertEquals(0, $this->container->getParameter('cache.ttl'));
        $this->assertFalse($this->container->getParameter('async.enabled'));
        $this->assertFalse($this->container->getParameter('twig.cache'));
        $list = $this->container->getParameter('profile.allow_list');
        $this->assertIsArray($list);
        $this->assertContains('test', $list);
    }

  /**
   * @coverage Drutiny\Console\Command\ConfigGetCommand
   */
    public function testConfigGetCommand()
    {
        $input = new ArrayInput([
        'command' => 'config:get',
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('profile.library.fs', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\ExpressionReferenceCommand
   */
    public function testExpressionReferenceCommand()
    {
        $input = new ArrayInput([
        'command' => 'expression:reference',
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('Policy.succeeds', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\DomainSourceListCommand
   */
    public function testDomainSourceListCommand()
    {
        $filename = $this->testTmpDir . '/domains.yml';
        file_put_contents($filename, Yaml::dump(['foo.example.com']));
        $input = new ArrayInput([
        'command' => 'domain-source:list',
        'target' => 'none:none',
        '--yaml-filepath' => $filename,
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('foo.example.com', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\KeywordAuditCommand
   */
    public function testKeywordAuditCommand()
    {
        $input = new ArrayInput([
        'command' => 'keyword:audit',
        'target' => 'none:none',
        '--keyword' => ['test'],
        '--yes' => null,
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code, "keyword:audit is not exiting on severity.");
        $this->assertStringContainsString('This policy should always error', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\LogsCommand
   */
    public function testLogsCommand()
    {
        $this->assertEquals('phpunit', $this->container->getParameter('log.name'));
        $this->container->get(LoggerInterface::class)->warning('This is a test for command "logs".');

        $input = new ArrayInput([
        'command' => 'logs'
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code, "keyword:audit is not exiting on severity.");
        $this->assertStringContainsString('This is a test for command "logs".', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\PluginListCommand
   */
    public function testPluginListCommand()
    {
        $input = new ArrayInput([
        'command' => 'plugin:list',
        '--format' => 'json'
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('http:user_agent', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\ProfileRunCommand
   */
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
        $this->assertStringContainsString('Always pass test policy', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\ProfileListCommand
   */
    public function testProfileListCommand()
    {
        $input = new ArrayInput([
        'command' => 'profile:list',
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('Test Profile', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\ProfileInfoCommand
   */
    public function testProfileInfoCommand()
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

  /**
   * @coverage Drutiny\Console\Command\PolicyListCommand
   */
    public function testPolicyListCommand()
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
   * @coverage Drutiny\Console\Command\PolicyDownloadCommand
   */
    public function testPolicyDownloadCommand()
    {
        $policy_fs = $this->container->getParameter('policy.library.fs');
        $filename = $policy_fs . '/Test-Pass.policy.yml';
        $this->assertFileDoesNotExist($filename);
        $input = new ArrayInput([
        'command' => 'policy:download',
        'policy' => 'Test:Pass',
        'source' => 'localfs',
        '--no-interaction' => null
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertStringContainsString($filename, $this->output->fetch());
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);

        $this->assertFileExists($filename);

        unlink($filename);
    }

  /**
   * @coverage Drutiny\Console\Command\PolicyAuditCommand
   */
    public function testPolicyAuditCommand()
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

  /**
   * @coverage Drutiny\Console\Command\PolicyInfoCommand
   */
    public function testPolicyInfoCommand()
    {
        $input = new ArrayInput([
        'command' => 'policy:info',
        'policy' => 'Test:Pass'
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertStringContainsString('This policy should always pass', $this->output->fetch());
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
    }

  /**
   * @coverage Drutiny\Console\Command\PolicyPushCommand
   */
    public function testPolicyPushCommand()
    {
        $input = new ArrayInput([
        'command' => 'policy:push',
        'policy' => 'Test:Pass',
        'source' => 'test'
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertStringContainsString('Policy Test:Pass successfully pushed to test', $this->output->fetch());
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
    }

  /**
   * @coverage Drutiny\Console\Command\AuditInfoCommand
   */
    public function testAuditInfoCommand()
    {
        $input = new ArrayInput([
        'command' => 'audit:info',
        'audit' => 'Drutiny\Audit\AlwaysPass'
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('Audit Info', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\AuditRunCommand
   */
    public function testAuditRunCommand()
    {
        $input = new ArrayInput([
        'command' => 'audit:run',
        'audit' => 'Drutiny\Audit\AlwaysPass',
        'target' => 'none:none'
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('Drutiny\Audit\AlwaysPass', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\PolicySourcesCommand
   */
    public function testPolicySourcesCommand()
    {
        $input = new ArrayInput([
        'command' => 'policy:sources',
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('localfs', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\ProfileSourcesCommand
   */
    public function testProfileSourcesCommand()
    {
        $input = new ArrayInput([
        'command' => 'profile:sources',
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('localfs', $this->output->fetch());
    }

  /**
   * @coverage Drutiny\Console\Command\TargetMetadataCommand
   */
    public function testTargetMetadataCommand()
    {
        $input = new ArrayInput([
        'command' => 'target:info',
        'target' => 'none:none',
        '--uri' => 'https://foo.example.com/',
        '--format' => 'yaml'
        ]);

        $code = $this->application->run($input, $this->output);
        $this->assertIsInt($code);
        $this->assertStringContainsString('https://foo.example.com/', $this->output->fetch());
        $this->assertEquals(0, $code);
    }
}
