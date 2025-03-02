<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\ProfileFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class ProfileShowCommand extends DrutinyBaseCommand
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
        ->setName('profile:show')
        ->setDescription('Show a profile definition.')
        ->addArgument(
            'profile',
            InputArgument::REQUIRED,
            'The name of the profile to show.'
        )
        ->addOption(
            'format',
            'f',
            InputOption::VALUE_OPTIONAL,
            'An output format. Default YAML. Support: yaml, json',
            'yaml'
        );
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLanguage($input);

        $profile = $this->profileFactory->loadProfileByName($input->getArgument('profile'));
        $export = $profile->export();

        if (isset($export['format']['html']['content'])) {
            $export['format']['html']['content'] = str_replace("\r", '', $export['format']['html']['content']);
        }

        switch ($input->getOption('format')) {
            case 'json':
                $format = json_encode($export, JSON_PRETTY_PRINT);
                break;
            default:
                $format = Yaml::dump($export, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                break;
        }

        $output->write($format);

        return 0;
    }
}
