<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drutiny\Console\Command;

use Drutiny\Settings;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * A console command for autowiring information.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @internal
 */
class ConfigGetCommand extends Command
{
    public function __construct(protected Settings $settings)
    {
        parent::__construct();
    }
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('config:get')
            ->setDescription('Lists configuration')
            ->setHelp('List all the configuraiton inside Drutiny')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Choose the output format (json or yaml)')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
       
        $config = $this->settings->getAll();
        ksort($config);

        if ($input->hasOption('format') && $input->getOption('format')) {
            $output->write(match ($input->getOption('format')) {
                'yaml' => Yaml::dump($config),
                'json' => json_encode($config),
                default => throw new InvalidArgumentException("No such format. Please specify 'json' or 'yaml'.")
            });
            return 0;
        }

        $rows = [];
        foreach ($config as $key => $value) {
            $rows[$key] = [$key, Yaml::dump($value)];
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('Configuration');
        $io->text('The following configuration can be customised:');
        $io->table(['Key', 'Value (YAML formatted)'], $rows);

        return 0;
    }
}
