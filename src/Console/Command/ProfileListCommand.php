<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\ProfileFactory;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *
 */
class ProfileListCommand extends DrutinyBaseCommand
{
    use LanguageCommandTrait;

    public function __construct(
        protected ProgressBar $progressBar,
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
        ->setName('profile:list')
        ->setDescription('Show all profiles available.')
        ->addOption(
            'source',
            's',
            InputOption::VALUE_OPTIONAL,
            'Filter by source'
        );
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLanguage($input);
        $render = new SymfonyStyle($input, $output);
        $this->progressBar->start(1);
        $this->progressBar->setMessage("Pulling profiles from profile sources...");
        $profiles = $this->profileFactory->getProfileList();

        if ($source_filter = $input->getOption('source')) {
            $this->progressBar->setMessage("Filtering profiles by source: $source_filter");
            $profiles = array_filter($profiles, function ($profile) use ($source_filter) {
                if ($source_filter == $profile['source']) {
                    return true;
                }
                if ($source_filter == preg_replace('/\<.+\>/U', '', $profile['source'])) {
                    return true;
                }
                return false;
            });
        }

      // Build array of table rows.
        $rows = array_map(function ($profile) {
            return [$profile['title'], $profile['name'], $profile['source']];
        }, $profiles);

      // Sort rows by profile name alphabetically.
        usort($rows, function ($a, $b) {
            if ($a[1] === $b[1]) {
                return 0;
            }
            $sort = [$a[1], $b[1]];
            sort($sort);
            return $a[1] === $sort[0] ? -1 : 1;
        });
        $this->progressBar->finish();
        $this->progressBar->clear();
        $render->table(['Profile', 'Name', 'Source'], $rows);
        $render->note("Use drutiny profile:info to view more information about a profile.");
        return 0;
    }
}
