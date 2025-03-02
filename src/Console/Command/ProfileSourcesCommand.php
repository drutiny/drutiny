<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Drutiny\PolicySource\PushablePolicySourceInterface;
use Drutiny\ProfileFactory;

/**
 *
 */
class ProfileSourcesCommand extends DrutinyBaseCommand
{
    use LanguageCommandTrait;

    public function __construct(
        protected ProfileFactory $profileFactory,
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
        ->setName('profile:sources')
        ->setDescription('Show all profile sources.');
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->profileFactory->sources as $source) {
            $rows[] = [$source->name, get_class($this->profileFactory->getSource($source->name)), $source->weight];
        }

        $io = new SymfonyStyle($input, $output);
        $headers = ['Source', 'Class', 'Weight'];
        $io->table($headers, $rows);

        return 0;
    }
}
