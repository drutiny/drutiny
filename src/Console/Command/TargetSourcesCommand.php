<?php

namespace Drutiny\Console\Command;

use Drutiny\Target\TargetFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Drutiny\Target\TargetInterface;
use Drutiny\Target\TargetSourceInterface;
use ReflectionClass;

/**
 *
 */
class TargetSourcesCommand extends DrutinyBaseCommand
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
        ->setName('target:sources')
        ->setDescription('List the different types of target sources.');
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rows = [];
        foreach ($this->targetFactory->getTypes() as $type => $class) {
            $reflection = new ReflectionClass($class);
            $rows[] = [$type, $class, $reflection->implementsInterface(TargetSourceInterface::class) ? 'yes' : 'no'];
        }

        $io = new SymfonyStyle($input, $output);
        $io->table(['source', 'class', 'listable'], $rows);

        return 0;
    }
}
