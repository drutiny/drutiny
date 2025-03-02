<?php

namespace Drutiny\Console\Command;

use Drutiny\Settings;
use Phar;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Self update command.
 */
class PharExtractCommand extends DrutinyBaseCommand
{
    public function __construct(protected Settings $settings, protected LoggerInterface $logger)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
        ->setName('phar-extract')
        ->setHidden(true)
        ->setDescription('Extract phar release file.');
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return !empty(Phar::running());
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $release_dir = $this->settings->get('drutiny_releases_dir');
        $version = $this->getApplication()->getVersion();
        $extraction_location = "$release_dir/$version";
        $release_bin = "$extraction_location/vendor/bin/drutiny";
        
        $fs = new Filesystem;

        // Release previously extracted we can passthru to it.
        if ($fs->exists($release_bin)) {
            return $this->callExtracted($release_bin);
        }

        if ($fs->exists($extraction_location)) {
            $io->error("$version already exists at $extraction_location but doesn't have a known callable drutiny bin. Expecting $release_bin.");
            return 1;
        }

        $fs->mkdir($extraction_location);

        $phar = new Phar(Phar::running());
        $phar->extractTo($extraction_location);
        $io->success("$version extracted to $extraction_location.");

        if (!$fs->exists($release_bin)) {
            $io->error("Cannot find drutiny bin in release. Expecting $release_bin.");
            return 2;
        }

        $bin = $this->settings->get('drutiny_release_bin');
        $bin_dir = dirname($bin);
        $fs->mkdir($bin_dir);

        if ($fs->exists($bin)) {
            unlink($bin);
        }
        if (symlink($release_bin, $bin)) {
            $io->success("$version is now accessible from $bin. ");
        }

        return $this->callExtracted($bin);
    }

    protected function callExtracted($bin):int
    {
        $args = $_SERVER['argv'];
        $args[0] = $bin;
        $process = new Process($args);
        $process->setTty(true);
        $process->setPty(true);
        $process->setTimeout(null);
        $this->logger
            ->withName('phar-launcher')
            ->info(Phar::running() . ' calling ' . $process->getCommandLine());
        return $process->run();
    }
}
