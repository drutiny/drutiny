<?php

namespace Drutiny\Console\Command;

use Composer\Semver\Semver;
use DateTime;
use Drutiny\Settings;
use Phar;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Self update command.
 */
#[AsCommand(
    name: 'version:clean',
    description: 'Remove old versions of drutiny releases.',
)]
class VersionCleanUpCommand extends DrutinyBaseCommand
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
        $this->addArgument('version_constraint', InputArgument::OPTIONAL, 'A semver constraint for releases to comply with or be removed.')
            ->addOption('before', 'b', InputOption::VALUE_OPTIONAL, 'Delete versions installed before this given date.');
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

        if ($before = $input->getOption('before')) {
            $before = new DateTime($before);
        }
        $constraint = $input->getArgument('version_constraint');

        $finder = new Finder;
        $finder->directories()->in($release_dir)->depth(0);
        $versions = $rows = [];

        foreach ($finder as $dir) {
            // Cannot remove current version.
            if ($current_version == $dir->getBasename()) {
                continue;
            }
            list($version,) = explode(' ', $dir->getBasename(), 2);

            if (isset($constraint) && Semver::satisfies($version, $constraint)) {
                continue;
            }

            if (isset($before) && $dir->getMTime() > $before->format('U')) {
                continue;
            }
            $rows[] = [$version, date('Y-m-d H:i:s', $dir->getMTime())];
            $versions[$version] = $dir->getRealPath();
        }

        if (empty($versions)) {
            $io->success("No versions to clean up.");
            return Command::SUCCESS;
        }

        $io->title("Clean up versions");
        $io->table(['Version', 'Installed on'], $rows);
        
        if (!$io->confirm("Remove old versions?")) {
            $io->comment("cancelled");
            return Command::SUCCESS;
        }
        
        $fs = new Filesystem;

        foreach ($versions as $version => $path) {
            $io->warning("Removing $version ($path)..");
            $fs->remove($path);
        }

        return Command::SUCCESS;
    }
}
