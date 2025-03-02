<?php
namespace Drutiny\Target\Transport;

use Drutiny\Helper\ProcessUtility;
use Drutiny\LocalCommand;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class SshTransport implements TransportInterface
{
    const MAX_SSH_COMMAND_LENGTH = 200000;
    protected array $sshConfig = [];
    protected LoggerInterface $logger;

    public function __construct(
        protected LocalCommand $localCommand
    ) {
        $this->logger = $localCommand->logger;
        if ($this->logger instanceof Logger) {
            $ns = explode('\\', get_class($this));
            $this->logger = $this->logger->withName(array_pop($ns));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send(Process $command, ?callable $processor = null)
    {
        ProcessUtility::mergeEnv($command, $this->localCommand->getEnvVars());
        $commandline = ProcessUtility::replacePlaceholders($command)->getCommandLine();
        $this->logger->debug("Transporting command: ".$commandline);
        // Commands that are really big might require some kind of real compression.
        if (function_exists('gzencode') && strlen($commandline) > static::MAX_SSH_COMMAND_LENGTH) {
            $comandline = 'echo ' . base64_encode(gzencode($commandline, 9));
            $comandline .= ' | base64 --decode | gzip -d';
        }
        $commandline = $this->getRemoteCall() . sprintf(" 'echo %s | base64 --decode | sh'", base64_encode($commandline));
        $ssh_command = Process::fromShellCommandline($commandline);
        ProcessUtility::copyConfiguration($command, $ssh_command);
        return $this->localCommand->run($ssh_command, $processor);
    }

    /**
     * Set SSH according to the ssh manual.
     */
    public function setConfig($key, $value)
    {
        $this->sshConfig[$key] = $value;
        return $this;
    }
  
    /**
     * Download a resource from a source location.
     */
    public function downloadFile($source, $location)
    {
        return $this->send(Process::fromShellCommandline(sprintf('test -f % s && cat %s', $source, $source)), function ($output) use ($location) {
            if (empty($output)) {
                return false;
            }
            file_put_contents($location, $output);
            return true;
        }, 0);
    }
  
    /**
     * Formulate an SSH command. E.g. ssh -o User=foo hostname.bar
     */
    protected function getRemoteCall($bin = 'ssh')
    {
        $args = [$bin];
        $options = $this->sshConfig;
        if (!isset($this->sshConfig['Host'])) {
            throw new \InvalidArgumentException("Missing 'Host' option in SSH Config.");
        }
  
      // Host is not a command line support option and must be passed as an argument.
        $host = $this->sshConfig['Host'];
        unset($options['Host']);

      // Bespoke support for an SSH config file.
        if (isset($options['File'])) {
            $args[] = '-F';
            $args[] = $options['File'];
            unset($options['File']);
        }
  
        foreach ($options as $key => $value) {
            $args[] = '-o';
            $args[] = sprintf('%s=%s', $key, $value);
        }
        $args[] = $host;
        return implode(' ', $args);
    }
}
