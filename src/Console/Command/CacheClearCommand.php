<?php

namespace Drutiny\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Cache\Adapter\FilesystemAdapter as Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 *
 */
class CacheClearCommand extends Command
{
    protected $container;
    protected $cache;

    public function __construct(ContainerInterface $container, CacheInterface $cache)
    {
      $this->cache = $cache;
      $this->container = $container;
      parent::__construct();
    }

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
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $adaptors = [
          $this->container->get('cache'),
          $this->container->get('cache.global'),
        ];

        if ($input->getOption('twig-only')) {
          $adaptors = [];
        }

        $cid = $input->getOption('cid');

        foreach ($adaptors as $adaptor) {
          empty($cid) ? $adaptor->clear() : $adaptor->delete($cid);
        }

        $dir = $this->container->getParameter('twig.cache');

        if (!file_exists($dir)) {
          $io->info('Cache is already cleared: ' . $dir);
          return 0;
        }
        if (!is_writable($dir)) {
          $io->error(sprintf('Cannot clear cache: %s is not writable.', $dir));
          return 0;
        }
        exec(sprintf('rm -rf %s', $dir), $output, $status);
        if ($status === 0) {
          $io->success('Cache is cleared: ' . $dir);
          return 0;
        }
        $io->error(sprintf('Cannot clear cache from %s. An error occured.', $dir));
        return 0;
    }
}
