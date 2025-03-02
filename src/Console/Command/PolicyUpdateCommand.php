<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\PolicyFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class PolicyUpdateCommand extends DrutinyBaseCommand
{
    use LanguageCommandTrait;
    public function __construct(
        protected ProgressBar $progressBar,
        protected LoggerInterface $logger,
        protected PolicyFactory $policyFactory,
        protected LanguageManager $languageManager
    ) {
        parent::__construct();
    }
  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('policy:update')
        ->setDescription('Updates all policies from their respective policy sources.')
        ->addOption(
            'source',
            's',
            InputOption::VALUE_OPTIONAL,
            'Update a specific policy source only.'
        );
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLanguage($input);

        if ($source = $input->getOption('source')) {
            $sources = [$this->policyFactory->getSource($source)];
        } else {
            $sources = array_map(fn ($s) => $this->policyFactory->getSource($s->name), $this->policyFactory->sources);
        }

        $this->progressBar->start(array_sum(array_map(function ($source) {
            return count($source->getList($this->languageManager));
        }, $sources)));

        foreach ($sources as $source) {
            $this->logger->notice("Updating " . $source->name);

            foreach ($source->refresh($this->languageManager) as $policy) {
                $this->progressBar->advance();
                $this->logger->notice($source->name . ': Updated "' . $policy->title . '"');
            }
        }

        $this->progressBar->finish();

        return 0;
    }
}
