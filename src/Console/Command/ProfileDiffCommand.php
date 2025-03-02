<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\Profile;
use Drutiny\ProfileFactory;
use LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 *
 */
class ProfileDiffCommand extends DrutinyBaseCommand
{

    public function __construct(
        protected Environment $twigEnvironment,
        protected ProfileFactory $profileFactory,
        protected LanguageManager $languageManager
    ) {
        parent::__construct();
    }

    use LanguageCommandTrait;

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('profile:diff')
        ->setDescription('Show a diff of a common profile from difference sources.')
        ->addArgument(
            'profile',
            InputArgument::REQUIRED,
            'The name of the profile to diff.'
        )
        ->addArgument(
            'source1',
            InputArgument::OPTIONAL,
            'The name of the source to load the original profile from.'
        )
        ->addArgument(
            'source2',
            InputArgument::OPTIONAL,
            'The name of the source to load the comparative profile from.'
        );
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        
        // Set global language used by profile sources.
        $this->initLanguage($input);

        $profile_name = $input->getArgument('profile');

        if ($input->getArgument('source1') === null || $input->getArgument('source2') === null) {
            $profile_sources = $this->getSourcesByProfileName($profile_name);
            if (empty($profile_sources)) {
                throw new LogicException("There are no sources found for profile: $profile_name.");
            }
        }
        if ($input->getArgument('source1') === null) {
            $source = $io->choice(
                question: "Which source to diff as the original profile?",
                choices: array_keys($profile_sources),
            );
            $source1 = $profile_sources[$source];
            unset($profile_sources[$source]);
        } else {
            $source1 = $this->profileFactory->getSource($input->getArgument('source1'));
            if (isset($profile_sources)) {
                unset($profile_sources[$source1->name]);
            }
        }

        if ($input->getArgument('source2') === null && empty($profile_sources)) {
            throw new LogicException("There are not enough sources to diff this profile.");
        } elseif ($input->getArgument('source2') === null) {
            $source2 = count($profile_sources) == 1 ? array_shift($profile_sources) : $profile_sources[$io->choice(
                question: "Which source to diff as the comparative profile?",
                choices: array_keys($profile_sources),
            )];
        } else {
            $source2 = $this->profileFactory->getSource($input->getArgument('source2'));
        }

        $profile1_list = $source1->getList($this->languageManager);
        $profile2_list = $source2->getList($this->languageManager);

        $profile_name = $input->getArgument('profile');

        if (!isset($profile1_list[$profile_name])) {
            $io->error("profile '$profile_name' does not exist in source: " . $input->getArgument('source1'));
            return 1;
        }

        if (!isset($profile2_list[$profile_name])) {
            $io->error("profile '$profile_name' does not exist in source: " . $input->getArgument('source2'));
            return 1;
        }

        $profile1 = $source1->load($profile1_list[$profile_name]);
        $profile2 = $source2->load($profile2_list[$profile_name]);

        $profile1_formatted = $this->prepareProfile($profile1);
        $profile2_formatted = $this->prepareProfile($profile2);

        $builder = new UnifiedDiffOutputBuilder(
            header: '--- ' . $input->getArgument('source1') . " {$profile1->uri}\n+++ " . $input->getArgument('source2') . ' ' . $profile2->uri,
            addLineNumbers: false
        );
        $differ = new Differ($builder);
        $lines = explode(PHP_EOL, $differ->diff($profile1_formatted, $profile2_formatted));
        foreach ($lines as $line) {
            $io->writeln(match (substr($line, 0, 1)) {
                '+' => "<fg=green>$line</>",
                '-' => "<fg=red>$line</>",
                default => $line
            });
        }
        return 0;
    }

    /**
     * @return \Drutiny\profileSource\AbstractprofileSource[]
     */
    protected function getSourcesByProfileName(string $profile_name):array
    {
        $profile_sources = [];

        foreach ($this->profileFactory->sources as $source) {
            $list = $this->profileFactory->getSource($source->name)->getList($this->languageManager);

            if (isset($list[$profile_name])) {
                $profile_sources[$source->name] = $this->profileFactory->getSource($source->name);
            }
        }
        return $profile_sources;
    }

    protected function prepareProfile(profile $profile)
    {
        $export = $profile->export();

        unset($export['source']);
        unset($export['uri']);

        ksort($export);

        return Yaml::dump($export, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
