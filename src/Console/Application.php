<?php

namespace Drutiny\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Yaml\Yaml;
use Drutiny\Kernel;
use Drutiny\Plugin\PluginRequiredException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Application extends BaseApplication
{
    private $kernel;
    private $commandsRegistered = false;
    private $registrationErrors = [];

    public function __construct(Kernel $kernel, $version)
    {
        $this->kernel = $kernel;
        parent::__construct($kernel->getContainer()->getParameter('name'), $version);
        $this->setDispatcher($kernel->getContainer()->get('event_dispatcher'));
    }

    /**
     * Gets the Kernel associated with this Console.
     *
     * @return KernelInterface A KernelInterface instance
     */
    public function getKernel()
    {
        return $this->kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input = null, OutputInterface $output = null)
    {
      $this->getKernel()->getContainer()->set('output', $output);
      $this->checkForUpdates($output);
      $this->registerCommands();

      if ($this->registrationErrors) {
          $this->renderRegistrationErrors($input, $output);
      }

      $this->getKernel()->dispatchEvent('application.run', [
        'input' => $input,
        'output' => $output,
      ]);
      return parent::doRun($input, $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
    {
        $startTimer = microtime(TRUE);
        switch ($output->getVerbosity()) {
          case OutputInterface::VERBOSITY_VERBOSE:
            $this->kernel->getContainer()->get('logger.logfile')->setLevel('NOTICE');
            break;
          case OutputInterface::VERBOSITY_VERY_VERBOSE:
            $this->kernel->getContainer()->get('logger.logfile')->setLevel('INFO');
            break;
          case OutputInterface::VERBOSITY_DEBUG:
            $this->kernel->getContainer()->get('logger.logfile')->setLevel('DEBUG');
            break;
          default:
            $this->kernel->getContainer()->get('logger.logfile')->setLevel('WARNING');
            break;
        }

        $this->getKernel()->dispatchEvent('command.run', [
          'command' => $command,
          'input' => $input,
          'output' => $output,
        ]);

        if (!$command instanceof ListCommand) {
            if ($this->registrationErrors) {
                $this->renderRegistrationErrors($input, $output);
                $this->registrationErrors = [];
            }

            return parent::doRunCommand($command, $input, $output);
        }


        $returnCode = parent::doRunCommand($command, $input, $output);
        $endTimer = microtime(TRUE);

        if ($this->registrationErrors) {
            $this->renderRegistrationErrors($input, $output);
            $this->registrationErrors = [];
        }
        $this->kernel->getContainer()->get('logger')->notice("Application Command {command} completed in {runtime} seconds.", [
          'command' => $command->getName(),
          'runtime' => ($endTimer-$startTimer),
        ]);

        return $returnCode;
    }

    /**
     * {@inheritdoc}
     */
    public function find($name)
    {
        $this->registerCommands();

        return parent::find($name);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        $this->registerCommands();

        $command = parent::get($name);

        if ($command instanceof ContainerAwareInterface) {
            $command->setContainer($this->kernel->getContainer());
        }

        return $command;
    }

    /**
     * {@inheritdoc}
     */
    public function all($namespace = null)
    {
        $this->registerCommands();

        return parent::all($namespace);
    }

    public function add(Command $command)
    {
        $this->registerCommands();

        return parent::add($command);
    }

    protected function registerCommands()
    {
        if ($this->commandsRegistered) {
            return;
        }

        $this->commandsRegistered = true;

        $container = $this->kernel->getContainer();
        $container->findTags();
        foreach ($container->findTaggedServiceIds('command') as $id => $definition) {
            try {
              $command = $container->get($id);
              $event = $this->getKernel()->dispatchEvent('application.command.add', [
                'command' => $command,
                'action.add' => true,
              ]);

              if ($event['action.add']) {
                $this->add($container->get($id));
              }
            }
            catch (PluginRequiredException $e) {
              $this->kernel->getContainer()->get('logger')->warning("Cannot initiatize command $id as it requires a plugin that is not setup: " . $e->getMessage());
            }
        }
    }

    private function renderRegistrationErrors(InputInterface $input, OutputInterface $output)
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        (new SymfonyStyle($input, $output))->warning('Some commands could not be registered:');

        foreach ($this->registrationErrors as $error) {
            $this->doRenderThrowable($error, $output);
        }
    }

    private function checkForUpdates(OutputInterface $output = null)
    {
      $container = $this->kernel->getContainer();

      // Check for 2.x drutiny credentials and migrate them if 3.x credentials are
      // not yet setup.
      $old_path = $container->getParameter('config.old_path');
      $config = $container->get('config');
      $creds = $container->get('credentials');

      // If 3.x creds are set or 2.x creds dont' exist, don't continue.
      if (!file_exists($old_path) || count($config->getNamespaces()) || count($creds->getNamespaces())) {
        return;
      }

      $map = [
        'sumologic' => 'sumologic',
        'github' => 'github',
        'cloudflare' => 'cloudflare',
        'acquia:cloud' => 'acquia_api_v2',
        'acquia:lift' => 'acquia_lift',
        'http' => 'http',
      ];

      $old_creds = Yaml::parseFile($old_path);

      foreach ($container->findTaggedServiceIds('plugin') as $id => $info) {
          $plugin = $container->get($id);

          if (!isset($map[$plugin->getName()])) {
            continue;
          }

          if (!isset($old_creds[$map[$plugin->getName()]])) {
            continue;
          }

          foreach ($old_creds[$map[$plugin->getName()]] as $field => $value) {
            $plugin->setField($field, $value);
          }

          $output->writeln("Migrated plugin credentials for " . $plugin->getName() . ".");
      }

    }
}
