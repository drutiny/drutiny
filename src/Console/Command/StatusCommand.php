<?php

namespace Drutiny\Console\Command;

use Async\ForkInterface;
use Async\MessageException;
use Async\React\Client;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Event\RuntimeDependencyCheckEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 *
 */
class StatusCommand extends DrutinyBaseCommand
{

    public function __construct(protected EventDispatcher $eventDispatcher)
    {
        parent::__construct();
    }
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
        ->setName('status')
        ->setDescription('Review key details about Drutiny\'s runtime environment')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $headers = ['Criteria', 'Status', 'Value', 'Details'];
        $rows = [];

        $event = new RuntimeDependencyCheckEvent();
        $this->eventDispatcher->dispatch($event, 'status');

        foreach ($event->getRuntimeDependencies() as $dependency) {
            $rows[] = [
              $dependency->getName(),
              $dependency->getStatus() ? 'Pass' : 'Fail',
              $dependency->getValue(),
              $dependency->getDetails(),
            ];
        }

        $style->table($headers, $rows);

        // Check for any actively running drutiny forks.
        try {
            $status = Client::getServerStatus()->getPayload();

            $style->title('Active audit forks');
            $style->text("These are forks actively working on audits. Use the fork ID");
            $style->text("to filter logs and identify what that fork is doing. The fork");
            $style->text("ID is also the process ID and can be found using `ps aux`");
          
            $pid_statuses = array_map(function ($path) {
                $bits = explode('/', $path);
                pcntl_waitpid(array_pop($bits), $status, WNOHANG);
                return $status;
            }, $status['leases']);
            $row = fn($k, $v, $s) => ["/fork/$k", date('c', $v), $s];
            $style->table(['pid', 'started', 'status'], array_map($row, array_keys($status['leases']), array_values($status['leases']), $pid_statuses));

            $style->title('Current stored messages');
            $style->text("These are audits completed by forks and stored ready to be retrieved by");
            $style->text("the Assessment. There are " . count($status['store']) . " stored messages:");
            $rows = [];
            foreach ($status['store'] as $path) {
                $message = $this->getMessage($path);
                if (!($message instanceof AuditResponse)) {
                    continue;
                }
                $rows[] = [$path, $message->getPolicy()->name, $message->getType()];
            }
            $style->table(['path', 'policy', 'result'], $rows);
        } catch (MessageException $e) {
          // This just means the server is running atm. Nothing to do.
            $style->text("No active audits running.");
        }

        return 0;
    }

    /**
     * Get a message result from async.
     */
    protected function getMessage($path)
    {
        $message = Client::get($path)->getPayload();
        if ($message instanceof ForkInterface) {
            $message = $message->getResult();
        }
        return $message;
    }
}
