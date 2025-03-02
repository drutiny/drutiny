<?php

namespace Drutiny\Console\Command;

use Drutiny\Settings;
use Phar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Self update command.
 */
class VersionSelectCommand extends DrutinyBaseCommand
{
    public function __construct(
        protected Settings $settings
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
        ->setName('version:select')
        ->setDescription('Use an available version of this tool.');
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return file_exists($this->settings->get('drutiny_releases_dir'));
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $release_dir = $this->settings->get('drutiny_releases_dir');
        $current_version = $this->getApplication()->getVersion();

        $finder = new Finder;
        $finder->directories()->in($release_dir)->depth(0);
        $versions = [];
        foreach ($finder as $dir) {
            $choice = $dir->getBasename();
            if ($current_version == $dir->getBasename()) {
                $choice = $dir->getBasename() . " (current version)";
            }
            $versions[] = $choice;
        }
        $io->title("Currently using $current_version");
        $choice = $io->choice("Which version would you like to use?", $versions);

        $release_bin = "$release_dir/$choice/vendor/bin/drutiny";

        if (!file_exists($release_bin)) {
            $io->error("$release_bin does not exist. Cannot set version to $choice.");
            return 1;
        }
        
        $fs = new Filesystem;

        $bin = $this->settings->get('drutiny_release_bin');
        $fs->mkdir(dirname($bin));

        if ($fs->exists($bin)) {
            unlink($bin);
        }
        if (symlink($release_bin, $bin)) {
            $io->success("Version $choice is now accessible from $bin. ");
        }

        return 0;
    }
}
