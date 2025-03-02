<?php

namespace Drutiny\Console\Command;

use Monolog\Handler\StreamHandler;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 *
 */
class LogsCommand extends DrutinyBaseCommand
{
    public function __construct(protected StreamHandler $logFile)
    {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('logs')
        ->setDescription('Show recent logs from current day.')
        ->addOption(
            'tail',
            'f',
            InputOption::VALUE_NONE
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output):int
    {
        if ($input->getOption('tail')) {
            $output->writeln("Tailing log: " . $this->logFile->getUrl());
            $process = Process::fromShellCommandline(sprintf('tail -f -n 20 %s', $this->logFile->getUrl()));
          // Set timeout till log rotates (by day).
            $process->setTimeout(strtotime('tomorrow') - time());
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($this->formatLogs($buffer));
            });
        } else {
            $process = Process::fromShellCommandline(sprintf('cat %s', $this->logFile->getUrl()));
          
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
        }
        return 0;
    }

    protected function formatLogs(string $buffer): string
    {
        return implode(PHP_EOL, array_map(function ($line) {
            if (!preg_match('/\[(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}.\d+\+\d{2}:\d{2})\]\[pid:(\d+) (\d+ [GKM]?B)\] ([\w+\.]+)\.(\w+): (.*)/', $line, $matches)) {
                return $line;
            }
            $status_color = match ($matches[5]) {
                'ERROR' => 'red',
                'WARNING' => 'yellow',
                'NOTICE' => 'green',
                'INFO' => 'blue',
                default => 'white',
            };
            return strtr('<fg=green>[{datetime}][pid: {pid} {memory_usage}]</> {name}.{status}: {message}', [
            '{datetime}' => $matches[1],
            '{pid}' => $matches[2],
            '{memory_usage}' => $matches[3],
            '{status}' => "<fg=$status_color>{$matches[5]}</>",
            '{name}' => $matches[4],
            '{message}' => $matches[6]
            ]);
        },
        explode(PHP_EOL, $buffer)));
    }
}
