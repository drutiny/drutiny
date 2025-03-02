<?php

namespace Drutiny\Target\Transport;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * RemoteService over Teleport (TSH).
 */
class TshTransport extends SshTransport
{

    const ERROR_AMBIGUOUS_HOST_MSG = 'ambiguous host could match multiple nodes';

    protected $tshConfig = [];

  /**
   * {@inheritdoc}
   */
    public function send(Process $command, ?callable $processor = null)
    {
        try {
            return $this->sendAndProcess($command, $processor);
        }
      // Look for teleport issue where an ambiguous host was detected.
        catch (ProcessFailedException $e) {
            $err = $e->getProcess()->getErrorOutput();
            if (strpos($err, self::ERROR_AMBIGUOUS_HOST_MSG) === false) {
                throw $e;
            }
            $output = $e->getMessage();
            $host = preg_quote($this->sshConfig['Host']);

          // Choose the first node found.
            if (!preg_match("/$host ([0-9a-z\-]+) /", $output, $matches)) {
                throw $e;
            }
            $node = $matches[1];

            $this->logger->warning("Teleport found multiple nodes for {$this->sshConfig['Host']}. Using first found: $node.");

          // Set future calls to use the Node rather than the hostname to avoid
          // ambiguous errors for the remainder of the process.
            $this->sshConfig['Host'] = $node;

          // Retry.
            return $this->sendAndProcess($command, $processor);
        }
    }

  /**
   * Send and process.
   *
   * TSH can return command characters that mess up output
   * so we will remove it here.
   */
    private function sendAndProcess(Process $command, ?callable $processor = null)
    {

        if (is_callable($processor)) {
            $reflect = new \ReflectionFunction($processor);
            $params = $reflect->getParameters();

            if (!empty($params) && ($params[0]->getType() == Process::class)) {
                return parent::send($command, function (Process $process, CacheItemInterface $item) use ($processor) {
                    return self::preprocess($process, $item, $processor);
                });
            }
        }
        return parent::send($command, function (string $output, CacheItemInterface $item) use ($processor) {
            return self::preprocess($output, $item, $processor);
        });
    }

  /**
   * Preprocess output from a TSH call to remove command characters.
   */
    public static function preprocess(Process|string $process, CacheItemInterface $item, ?callable $processor = null)
    {
        $output = is_string($process) ? $process : $process->getOutput();
      // @see https://unix.stackexchange.com/questions/14684/removing-control-chars-including-console-codes-colours-from-script-output/14707#14707
        $output = preg_replace('/ \e[ #%()*+\-.\/]. | \e\[ [ -?]* [@-~] | \e\] .*? (?:\e\\|[\a\x9c]) | \e[P^_] .*? (?:\e\\|\x9c) | \e. /x', '', $output);
        if (is_callable($processor) && $process instanceof Process) {
            $reflect = new \ReflectionFunction($processor);
            $params = $reflect->getParameters();

            if (!empty($params) && ($params[0]->getType() == Process::class)) {
                $process->clearOutput();
                $process->addOutput($output);
                return $processor($process, $item);
            }
        }
        return $processor ? $processor($output, $item) : $output;
    }

    public function setTshConfig(string $option, string $value)
    {
        $this->tshConfig[$option] = $value;
        return $this;
    }

  /**
   * Formulate an SSH command. E.g. ssh -o User=foo hostname.bar
   */
    protected function getRemoteCall($bin = 'ssh')
    {
        $args = ['tsh ' . $bin];
        $options = $this->sshConfig;
        if (!isset($this->sshConfig['Host'])) {
            throw new \InvalidArgumentException("Missing 'Host' option in SSH Config.");
        }

      // Host is not a command line support option and must be passed as an argument.
        $host = $this->sshConfig['Host'];
        unset($options['Host']);

        if (isset($options['User'])) {
            $host = $options['User'].'@'.$host;
            unset($options['User']);
        }

        foreach ($this->tshConfig as $key => $value) {
            $args[] = sprintf('--' . $key . '=%s', $value);
        }
    
        foreach ($options as $key => $value) {
            $args[] = '-o';
            $args[] = sprintf('%s=%s', $key, $value);
        }
        $args[] = $host;
        return implode(' ', $args);
    }
}
