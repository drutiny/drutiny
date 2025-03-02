<?php

namespace Drutiny\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Drutiny\Plugin\PluginRequiredException;
use Drutiny\Settings;
use Psr\Container\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class PluginListCommand extends Command
{
    public function __construct(
        protected Settings $settings,
        protected ContainerInterface $container
    ) {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('plugin:list')
        ->setDescription('List all available plugins.')
        ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Choose the output format (json or yaml)');
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        foreach ($this->settings->get('plugin.registry') as $name => $id) {
            $plugin = $this->container->get($id);
            
            if ($plugin->isHidden()) {
                continue;
            }

            $state = $plugin->isInstalled() ? 'Installed' : 'Not Installed';
            $rows[$name] = [$name, $state];
        }
        ksort($rows);

        $output->write(match ($input->getOption('format')) {
            'json' => json_encode($rows),
            'yaml' => Yaml::dump($rows),
            default => $io->table(['Namespace', 'Status'], $rows) ?? ''
        });

        return 0;
    }
}
