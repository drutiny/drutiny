<?php

namespace Drutiny\Console\Command;

use Drutiny\Target\TargetExport;
use Drutiny\Target\TargetFactory;
use InvalidArgumentException;
use League\Csv\InvalidArgument;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class TargetMetadataCommand extends DrutinyBaseCommand
{
    public function __construct(
        protected TargetFactory $targetFactory,
        protected LoggerInterface $logger,
        protected ProgressBar $progressBar
    ) {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('target:info')
        ->setDescription('Display metatdata about a target.')
        ->addArgument(
            'target',
            InputArgument::REQUIRED,
            'A target reference.'
        )
        ->addOption(
            'uri',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Provide URLs to run against the target. Useful for multisite installs. Accepts multiple arguments.',
            null
        )
        ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Choose the output format (json or yaml)')
        ->addOption('export', null, InputOption::VALUE_NONE, 'Export the target data to a temp file.');
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->progressBar->start();
        $this->progressBar->setMessage("Loading target..");
        $this->progressBar->advance();
     
        $target = $this->targetFactory->create(
            $input->getArgument('target'),
            $input->getOption('uri')
        );
        
        $this->progressBar->advance();

        $io = new SymfonyStyle($input, $output);

        $rows = $object = [];

        foreach ($target->getPropertyList() as $key) {
            $value = $target->getProperty($key);
            $object[$key] = $value;
            $value = is_object($value) ? '<object> (' . get_class($value) . ')'  : '<'.gettype($value) . '> ' . Yaml::dump($value, 8, 2);
            $this->logger->debug("$key: $value");
            if (strlen($value) > 1024) {
                $value = substr($value, 0, 1024) . '...';
            }
            $rows[] = [$key, $value];
        }

        $this->progressBar->finish();

        $output->write(match ($input->getOption('format')) {
            'json' => json_encode($object, JSON_PRETTY_PRINT),
            'yaml' => Yaml::dump($object),
            default => $io->table(['Property', 'Value'], $rows) ?? '',
        });

        if ($input->getOption('export')) {
            $io->note("Written export to " . TargetExport::create($target)->toTemporaryFile());
        }

        return 0;
    }
}
