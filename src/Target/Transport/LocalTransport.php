<?php
namespace Drutiny\Target\Transport;

use Drutiny\LocalCommand;
use Symfony\Component\Process\Process;

class LocalTransport implements TransportInterface
{
    public function __construct(
        protected LocalCommand $localCommand
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function send(Process $command, ?callable $processor = null)
    {
        return $this->localCommand->run($command, $processor);
    }
}
