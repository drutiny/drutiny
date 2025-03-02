<?php

namespace Drutiny\Console\Command;

use Drutiny\CacheFactory;
use Drutiny\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 *
 */
class CacheClearCommand extends AbstractBaseCommand
{
    protected CacheFactory $cacheFactory;
    protected Settings $settings;


    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
        ->setName('cache:clear')
        ->setDescription('Clear the Drutiny cache')
        ->addOption(
            'cid',
            null,
            InputOption::VALUE_OPTIONAL,
            'The cache ID to purge from cache.'
        )
        ->addOption(
            'twig-only',
            't',
            InputOption::VALUE_NONE,
            'Purge the '
        )
        ->addOption(
            'include-source-cache',
            's',
            InputOption::VALUE_NONE,
            'Clear policy and profile source caches also.'
        )
        ;
    }

    /**
     * @inheritdoc
     */
    protected function doExecute(InputInterface $input, OutputInterface $output):int
    {
        $io = new SymfonyStyle($input, $output);
        $registry = $this->settings->get('cache.registry');
        $registry = $input->getOption('include-source-cache') ? array_merge($registry, $this->settings->get('source.cache.registry')) : $registry;
        $registry = $input->getOption('twig-only') ? [] : $registry;

        global $kernel;

        // Rebuild kernel caches which are not stored in cache registry.
        register_shutdown_function(function () use ($kernel) {
            $kernel->refresh();
        });

        $status = static::SUCCESS;

        $dir = $this->settings->get('twig.cache');
        if (!file_exists($dir)) {
            $io->comment('Cache is already cleared: ' . $dir);
        } else {
            if (!is_writable($dir)) {
                $io->error(sprintf('Cannot clear cache: %s is not writable.', $dir));
                $status = static::FAILURE;
            }
            exec(sprintf('rm -rf %s', $dir), $output, $status);
            $io->success('Cache is cleared: ' . $dir);
        }

        if ($input->getOption('twig-only')) {
            return $status;
        }

        $this->cacheFactory->clearAll();

        foreach ($this->cacheFactory->caches as $cid) {
            $io->success($cid . ' is cleared.');
        }

        return $status;
    }
}
