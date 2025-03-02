<?php

namespace Drutiny\Console;

use Drutiny\Settings;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Manage multiple Symfony Process objects at once.
 */
class ProcessManager
{

    /**
     * The group of processes
     *
     * @var \Symfony\Component\Process\Process[]
     */
    protected array $group = [];

    /**
     * The maximum number of concurrent processes to run at any one time.
     */
    public int $maxConcurrency = 7;

    /**
     * The unix timestamp when the first process was started in the group.
     */
    public readonly int $start;

    /**
     * An array of callable promises.
     *
     * @var \Closure[]
     */
    protected array $promises = [];

    public function __construct(protected LoggerInterface $logger, protected Settings $settings)
    {
    }

    public function add(Process $process, ?string $name = null): self
    {
        if (isset($name)) {
            if (isset($this->group[$name])) {
                throw new InvalidArgumentException("Cannot add named process. '$name' is already a named process.");
            }
            $this->group[$name] = $process;
        } else {
            $this->group[] = $process;
        }
        return $this;
    }

    /**
     * Iterate over the processes to check there status.
     *
     * Starts pending processes if new ones are available.
     */
    public function update():self
    {

        $active = $this->getActive();
        while (count($active) < $this->maxConcurrency) {
            $pending = $this->getPending();

            if (count($pending) == 0) {
                break;
            }

            if ($proc = array_shift($pending)) {
                $this->logger->debug('Starting process: ' . $proc->getCommandLine());
                if (!isset($this->start)) {
                    $this->start = time();
                }
                $proc->start();
            }
            $active = $this->getActive();
        }
        return $this;
    }

    /**
     * Wait for all processes to complete.
     */
    public function wait(): void
    {
        while (!$this->hasFinished()) {
            $this->update();
            sleep(1);
        }
    }

    /**
     * Stack a callback to run on the result when resolved.
     */
    public function then(\Closure $promise): self
    {
        $this->promises[] = $promise;
        return $this;
    }

    /**
     * Process the promises to yield a result.
     */
    public function resolve()
    {
        $this->wait();
        foreach ($this->promises as $promise) {
            if (!isset($result)) {
                $result = $promise($this->group);
                continue;
            }
            $result = $promise($result);
        }
        return $result ?? null;
    }

    /**
     * Boolean for if all processes in the group have completed.
     *
     * This does not mean all processes completed successfully.
     */
    public function hasFinished(): bool
    {
        return count($this->group) == count($this->getCompleted());
    }

    /**
     * Get an array of processes yet to be started.
     *
     * @return Process[]
     */
    public function getPending(): array
    {
        return $this->filter(fn(Process $p) => !$p->isStarted());
    }

    /**
     * Get a list of processes active last time they were checked.
     *
     * @return Process[]
     */
    public function getActive(): array
    {
        return $this->filter(fn (Process $p) => $p->isRunning());
    }

    /**
     * Get a list of completed processes.
     *
     * This does not mean they were completed successfully.
     *
     * @return Process[]
     */
    public function getCompleted(): array
    {
        return $this->filter(fn (Process $p) => $p->isTerminated());
    }

    /**
     * Return a filtered list of processes based on a provided filter callback.
     *
     * @return Process[]
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->group, $callback);
    }

    /**
     * Create an array mapped from the processes in the group.
     *
     * @return static
     */
    public function map(callable $callback): array
    {
        $keys = array_keys($this->group);
        $values = array_map($callback, $this->group, $keys);
        return array_combine($keys, $values);
    }

    /**
     * Iterate over the process group.
     *
     * The callback can recieve two arguments: The process
     * and the position to process is in the array.
     */
    public function each(callable $callback):self
    {
        foreach ($this->group as $i => $proc) {
            $callback($proc, $i);
        }
        return $this;
    }

    public function length(): int
    {
        return count($this->group);
    }

    /**
     * Create a process to run a Drutiny command.
     */
    public function create(array $args, ...$opts):Process
    {
        if ($this->settings->get('drutiny_is_vendored')) {
            $cmd = $GLOBALS['_composer_bin_dir'] . '/drutiny';
        } else {
            $cmd = $this->settings->get('drutiny.core.directory') . '/bin/drutiny';
        }
        if (!file_exists($cmd)) {
            throw new InvalidArgumentException("Drutiny command not found at $cmd.");
        }
        array_unshift($args, $cmd);

        if (!array_key_exists('timeout', $opts)) {
            $opts['timeout'] = 3600;
        }

        return new Process($args, ...$opts);
    }
}
