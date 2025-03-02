<?php

namespace Drutiny\Console\Command;

use Drutiny\Target\InvalidTargetException;
use Drutiny\Target\TargetFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Drutiny\Target\TargetSourceInterface;

/**
 *
 */
class TargetSourceListCommand extends DrutinyBaseCommand
{

    public function __construct(
        protected TargetFactory $targetFactory
    ) {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('target:list')
        ->setDescription('List available targets from a given source')
        ->addArgument(
            'source',
            InputArgument::REQUIRED,
            'The name of a target source. See target:sources.'
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getArgument('source');
        $target = $this->targetFactory->mock($input->getArgument('source'));

        if (!($target instanceof TargetSourceInterface)) {
            throw new InvalidTargetException('Target source does not support listing available targets: ' . $source);
        }

        $io = new SymfonyStyle($input, $output);

        $rows = [];

        foreach ($target->getAvailableTargets() as $info) {
            $info += ['id' => '', 'uri' => '', 'name' => ''];
            $rows[] = [$source . ':' . $info['id'], $info['uri'], $info['name']];
        }
        $io->table(['Target', 'URI', 'Name'], $rows);

        return 0;
    }
}
