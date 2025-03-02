<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Drutiny\Profile\ProfileSource;
use Drutiny\ProfileFactory;
use Drutiny\Profile;
use Drutiny\Settings;
use Symfony\Component\Filesystem\Filesystem;

/**
 *
 */
class ProfileDownloadCommand extends Command
{
    use LanguageCommandTrait;

    public function __construct(
        protected ProfileFactory $profileFactory,
        protected LanguageManager $languageManager,
        protected Settings $settings
    ) {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('profile:download')
        ->setDescription('Download a remote profile locally.')
        ->addArgument(
            'profile',
            InputArgument::REQUIRED,
            'The name of the profile to download.'
        )
        ->addArgument(
            'source',
            InputArgument::OPTIONAL,
            'The source to download the profile from.'
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

        $profile = $this->profileFactory->loadProfileByName($input->getArgument('profile'));
        $export = $profile->export();
        foreach ($export['policies'] as &$override) {
            unset($override['name'], $override['weight']);
            if (isset($override['severity']) && $override['severity'] == 'normal') {
                unset($override['severity']);
            }
        }
        $filename = getcwd() . DIRECTORY_SEPARATOR . $this->settings->get('profile.library.fs') . "/{$profile->name}.profile.yml";
        $dir = dirname($filename);

        // Make sure the directory exists.
        $fs = new Filesystem;
        $fs->mkdir($dir);

        $export['uuid'] = $filename;

        // Convert \n\r to just \n
        $export['format']['html']['content'] = str_replace("\r", '', $export['format']['html']['content']);

        $output = Yaml::dump($export, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        file_put_contents($filename, $output);
        $render->success("$filename written.");
        return 0;
    }
}
