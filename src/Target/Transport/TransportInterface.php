<?php

namespace Drutiny\Target\Transport;

use Symfony\Component\Process\Process;

interface TransportInterface
{
    public function send(Process $command, ?callable $processor = null);
}
