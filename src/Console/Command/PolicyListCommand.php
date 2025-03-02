<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\PolicyFactory;
use Drutiny\Profile;
use Drutiny\ProfileFactory;
use Drutiny\Settings;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class PolicyListCommand extends DrutinyBaseCommand
{
    use LanguageCommandTrait;

    public function __construct(
        protected Settings $settings,
        protected ProgressBar $progressBar,
        protected ProfileFactory $profileFactory,
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
        ->setName('policy:list')
        ->setDescription('Show all policies available.')
        ->addOption(
            'filter',
            't',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Filter list by tag'
        )
        ->addOption(
            'source',
            's',
            InputOption::VALUE_OPTIONAL,
            'Filter by source'
        )
        ->addOption(
            'show-profile-usage',
            'u',
            InputOption::VALUE_NONE,
            'Show the number of profiles a policy is used in.'
        );
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->progressBar->start(4);

        $this->initLanguage($input);

        $this->progressBar->setMessage("Loading policy library from policy sources.");
        $list = $this->policyFactory->getPolicyList();

        if ($source_filter = $input->getOption('source')) {
            $this->progressBar->setMessage("Filtering policies by source: $source_filter");
            $list = array_filter($list, function ($policy) use ($source_filter) {
                if ($source_filter == $policy['source']) {
                    return true;
                }
                if ($source_filter == preg_replace('/\<.+\>/U', '', $policy['source'])) {
                    return true;
                }
                return false;
            });
        }
        $this->progressBar->advance();

        if ($input->getOption('show-profile-usage')) {
            $this->progressBar->setMessage("Mapping policy utilisation by profile.");
            $profiles = array_map(function ($profile) {
                return $this->profileFactory->loadProfileByName($profile['name']);
            }, $this->profileFactory->getProfileList());
        }
        
        $this->progressBar->advance();
        $rows = [];
        foreach ($list as $listedPolicy) {
            $row = [
                'description' => '<options=bold>' . wordwrap($listedPolicy['title'], 50) . '</>',
                'name' => $listedPolicy['name'],
                'source' => implode(', ', $listedPolicy['sources']),
            ];
            if ($input->getOption('show-profile-usage')) {
                $row['profile_util'] = count(array_filter($profiles, function (Profile $profile) use ($listedPolicy) {
                    return in_array($listedPolicy['name'], array_keys($profile->policies));
                }));
            }
            $rows[] = $row;
        }

        // Restrict visibility of policies to those in profile allow list.
        $allow_list = $this->settings->has('profile.allow_list') ? $this->settings->get('profile.allow_list') : [];
        if (!empty($allow_list) && $input->getOption('show-profile-usage')) {
            $rows = array_filter($rows, fn($r) => $r['profile_util']);
        }

        usort($rows, function ($a, $b) {
            $x = [strtolower($a['name']), strtolower($b['name'])];
            sort($x, SORT_STRING);

            return $x[0] == strtolower($a['name']) ? -1 : 1;
        });
        $this->progressBar->finish();

        $io = new SymfonyStyle($input, $output);
        $headers = ['Title', 'Name', 'Source'];
        if ($input->getOption('show-profile-usage')) {
            $headers[] = 'Profile Utilization';
        }
        $io->table($headers, $rows);

        $io->writeln(sprintf('%d policies, %d sources', count($rows), count(
            array_unique(array_map(fn ($p) => explode(',', $p['source'])[0], $rows))
        )));

        return 0;
    }

  /**
   *
   */
    protected function formatDescription($text)
    {
        $lines = explode(PHP_EOL, $text);
        $text = implode(' ', $lines);
        return wordwrap($text, 50);
    }
}
