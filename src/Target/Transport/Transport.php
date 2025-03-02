<?php

namespace Drutiny\Target\Transport;

use Symfony\Component\Process\Process;

class Transport implements TransportInterface
{
    protected function __construct(protected \Closure $callback)
    {
    }

    public static function create(callable $callback): self
    {
        return new static(fn (...$args) => $callback(...$args));
    }

    public function send(Process $command, ?callable $processor = null)
    {
        $callback = $this->callback;
        return $callback($command, $processor);
    }
}
