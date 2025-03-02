<?php

namespace Drutiny\Target\Transport;

use Drutiny\Helper\ProcessUtility;
use Drutiny\LocalCommand;
use Symfony\Component\Process\Process;

/**
 * RemoteService over Teleport (TSH).
 */
class DockerTransport implements TransportInterface
{

    protected string $container;
  
    public function __construct(protected LocalCommand $localCommand)
    {
    }
  
    public function setContainer(string $container_name):self
    {
        $this->container = $container_name;
        return $this;
    }
  
    /**
     * {@inheritdoc}
     */
    public function send(Process $command, ?callable $processor = null)
    {
        $cmd = sprintf("docker exec -t %s sh -c 'echo %s | base64 --decode | sh'", $this->container, base64_encode(ProcessUtility::replacePlaceholders($command)->getCommandLine()));
        return $this->localCommand->run(Process::fromShellCommandline($cmd), $processor);
    }
}
