<?php

namespace Drutiny\Console\Command;

use Drutiny\DomainSource;
use Drutiny\Target\TargetFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Run a profile and generate a report.
 */
class DomainSourceListCommand extends Command
{
    protected $domainSourceOptions;


    public function __construct(
        protected DomainSource $domainSource,
        protected LoggerInterface $logger,
        protected TargetFactory $targetFactory
    ) {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        parent::configure();

        $this
        ->setName('domain-source:list')
        ->setDescription('List domains from a given source.')
        ->addArgument(
            'target',
            InputArgument::REQUIRED,
            'The target to run the policy collection against.'
        );
        // Build a way for the command line to specify the options to derive
        // domains from their sources.
        foreach ($this->domainSource->getAllInputOptions() as $option) {
            $this->domainSourceOptions[] = $option->getName();
            $this->addOption(
                name: $option->getName(),
                mode: $this->domainSource->getInputOptionMode($option),
                description: $option->getDescription()
            );
        }
    }

  /**
   * {@inheritdoc}
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console = new SymfonyStyle($input, $output);
        $target = $this->targetFactory->create($input->getArgument('target'));

        $domains = [];
        $unique_domains = [];
        foreach ($this->parseDomainSourceOptions($input) as $source => $options) {
            $this->logger->notice("Loading domains from $source.");
            foreach ($this->domainSource->getDomains($target, $source, $options) as $domain) {
                $domains[] = [$source, $domain, isset($unique_domains[$domain]) ? implode(',', $unique_domains[$domain]) : ''];
                $unique_domains[$domain][] = $source;
            }
        }

        $console->table(['Source', 'Domain', 'Other sources'], $domains);
        $console->success(sprintf("Domain sources returned %s domains of which %s are unique.", count($domains), count($unique_domains)));

        return 0;
    }

    protected function parseDomainSourceOptions(InputInterface $input):array
    {
      // Load additional uris from domain-source
        $sources = [];
        foreach ($this->domainSourceOptions as $param) {
            $value = $input->getOption($param);

            if ($value === null) {
                continue;
            }

            if (strpos($param, '-') === false) {
                continue;
            }
            list($source, $name) = explode('-', $param, 2);
            $sources[$source][$name] = $value;
        }
        return $sources;
    }
}
