<?php

namespace Drutiny\Console\Helper;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * Pipes log into progress bar.
 */
class MonologProgressBarHandler extends AbstractProcessingHandler
{

    protected ProgressBar $progressBar;
    protected Terminal $terminal;
    protected OutputInterface $output;

    public bool $disabled = false;

    public function __construct(ProgressBar $progressBar, OutputInterface $output, Terminal $terminal, $level = Logger::NOTICE, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->progressBar = $progressBar;
        $this->terminal = $terminal;
        $this->output = ($output instanceof ConsoleOutputInterface) ? $output->getErrorOutput() : $output;
    }

  /**
   * {@inheritDoc}
   */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter('%message%');
    }

  /**
   * {@inheritdoc}
   */
    protected function write(LogRecord $record): void
    {
        $message = substr($record->formatted, 0, min($this->terminal->getWidth(), strlen($record->formatted)));
        $this->progressBar->setMessage($message);

        if (!$this->disabled && ($record['level'] >=  Logger::NOTICE)) {
            $this->progressBar->display();
        }
    }
}
