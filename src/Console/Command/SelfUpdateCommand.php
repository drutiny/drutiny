<?php

namespace Drutiny\Console\Command;

use Drutiny\Console\UpdateManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Self update command.
 */
class SelfUpdateCommand extends AbstractBaseCommand
{
    protected LoggerInterface $logger;
    protected UpdateManager $updateManager;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
        ->setName('self-update')
        ->setDescription('Update Drutiny by downloading latest phar release.');
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(InputInterface $input, OutputInterface $output):int
    {
        $exit = $this->updateManager->checkForUpdates($input, $output, []);

        if ($exit === Command::INVALID) {
            $output->writeln("<info>No updates available.</info>");
            return Command::SUCCESS;
        }
        return $exit;
    }
}
