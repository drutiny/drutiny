<?php

namespace Drutiny\Console\Helper;

use Closure;
use Drutiny\Console\ProcessManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Process\Process;

class ProcessManagerViewer
{

    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_TERMINATED = 'terminated';

    protected array $rows = [];
    protected array $processStatuses = [];
    protected Table $table;
    protected ConsoleSectionOutput $output;
    protected Closure $onStatusChange;
    protected Closure $onUpdate;
    private float $lastRender;

    public function __construct(ConsoleOutput $output, protected ProcessManager $processManager)
    {
        $this->output = $output->section();
        $this->table = new Table($this->output);

        $style = clone Table::getStyleDefinition('symfony-style-guide');
        $style->setCellHeaderFormat('<info>%s</info>');
        $this->table->setStyle($style);
        $this->table->setHeaders(['Process', 'Status', 'Timing']);
        $this->rows = $processManager->map(function (Process $p, string $id) {
            return [$id, $this::STATUS_PENDING, null];
        });
        $this->table->setRows($this->rows);
    }

    public function onStatusChange(Closure $callback):self
    {
        $this->onStatusChange = $callback;
        return $this;
    }

    public function onUpdate(Closure $callback):self
    {
        $this->onUpdate = $callback;
        return $this;
    }

    public function watchAndWait()
    {
        while (!$this->processManager->hasFinished()) {
            $rerender = false;
            $active = $this->processManager->getActive();
            foreach ($active as $name => $process) {
                $row_id = array_search($name, array_keys($this->rows));

                if (!isset($this->processStatuses[$name])) {
                    $status = self::STATUS_RUNNING;
                    if (isset($this->onStatusChange)) {
                        $callback = $this->onStatusChange;
                        $status = $callback($status, $name, $process);
                    }
                    $this->rows[$name][1] = $status;
                    $this->table->setRow($row_id, $this->rows[$name]);
                    $this->processStatuses[$name] = self::STATUS_RUNNING;
                } elseif (isset($this->onUpdate)) {
                    $callback = $this->onUpdate;
                    $update = $callback($process, $name);
                    if (is_string($update)) {
                        $this->rows[$name][1] = $update;
                        $this->table->setRow($row_id, $this->rows[$name]);
                        $this->processStatuses[$name] = self::STATUS_RUNNING;
                    }
                }
                $this->rows[$name][2] = round((microtime(true) - $process->getStartTime()), 2) . 's';
                $this->table->setRow($row_id, $this->rows[$name]);
                $rerender = true;
            }

            $completed = $this->processManager->getCompleted();
            foreach ($completed as $name => $process) {
                if (is_bool($this->processStatuses[$name])) {
                    continue;
                }
                $row_id = array_search($name, array_keys($this->rows));
                $status = self::STATUS_TERMINATED;
                if (isset($this->onStatusChange)) {
                    $callback = $this->onStatusChange;
                    $status = $callback($status, $name, $process);
                }
                $this->rows[$name][1] = $status;
                $this->table->setRow($row_id, $this->rows[$name]);
                $this->processStatuses[$name] = $process->isSuccessful();
                $rerender = true;
            }

            if ($rerender) {
                $this->render(count($active), count($completed));
            }
            usleep(500000);
            $this->processManager->update();
        }
        return $this->processManager;
    }

    public function render(int $active = 0, int $completed = 0):self
    {
        if (isset($this->lastRender)) {
            $this->clean();
        }
        $this->table->render();
        $this->output->writeln($active . ' Active. ' . count($this->rows) . '/' . $completed . ' Completed');
        $this->lastRender = microtime(true);
        return $this;
    }

    public function clean():self
    {
        $clear_lines = 5 + count($this->rows);
        $this->output->clear($clear_lines);
        return $this;
    }

    public function setHeaders(array $headers):self
    {
        $this->table->setHeaders($headers);
        return $this;
    }
}
