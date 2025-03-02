<?php

namespace Drutiny;

use Drutiny\Helper\ProcessUtility;
use Drutiny\Settings;
use Exception;
use Monolog\Logger;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

class LocalCommand
{

    public readonly LoggerInterface $logger;
    protected array $envVars = [];

    public function __construct(
        protected CacheInterface $cache,
        LoggerInterface $logger,
        protected Settings $settings
    ) {
        if ($logger instanceof Logger) {
            $logger = $logger->withName('process');
        }
        $this->logger = $logger;
    }

  /**
   * Run a local command.
   *
   * @param $cmd string
   *          The command you want to run.
   * @param $preProcess callable
   *          A callback to run to preprocess the output before caching.
   * @param $ttl string
   *          The time to live the processed result will line in cache.
   */
    public function run(Process|string $cmd, ?callable $outputProcessor = null, int $ttl = 3600)
    {
        $cmd = is_string($cmd) ? Process::fromShellCommandline($cmd) : $cmd;
        $cmd->setEnv(array_merge($this->envVars, $cmd->getEnv()));
    
        $process_timeout = $this->settings->has('process.timeout') ? $this->settings->get('process.timeout') : 600;
        $cmd->setTimeout($process_timeout);

        if (ProcessUtility::replacePlaceholders($cmd)->getCommandLine() == 'which ../vendor/drush/drush/drush || which drush-launcher || which drush.launcher || which drush') {
            throw new Exception("Where did you come from?");
        }
        $command = ProcessUtility::replacePlaceholders($cmd);

        return $this->cache->get($this->getCacheKey($command), function (CacheItemInterface $item) use ($command, $ttl, $outputProcessor) {
            $item->expiresAfter($ttl);
            try {
                $this->logger->debug($command->getCommandLine());
                if (is_callable($outputProcessor)) {
                    $reflect = new \ReflectionFunction($outputProcessor);
                    $params = $reflect->getParameters();

                    if (!empty($params) && ($params[0]->getType() == Process::class)) {
                        // This allows the output processor to evaluate the result of the
                        // process inclusive of its exit code.
                        $command->run();

                        // A process evaluating output processor must throw an exception
                        // to prevent caching of the result. This means a non-zero exit
                        // response can be cached.
                        $output = $outputProcessor($command, $item);
                        $this->logger->debug($command->getOutput());
                        return $output;
                    }
                }

              // mustRun means an non-zero exit code will throw an exception.
                $command->mustRun();
                $output = $command->getOutput();
        
                $this->logger->debug($output);

                if (isset($outputProcessor)) {
                    $output = $outputProcessor($output, $item);
                }
                return $output;
            } catch (ProcessFailedException $e) {
                $this->logger->error($e->getMessage());
                throw $e;
            }
        });
    }

    private function getCacheKey(Process $cmd):string
    {
        return hash('md5', $cmd->getCommandLine());
    }

  /**
   * Set an environmental variable.
   */
    public function setEnvVar(string $name, mixed $value):self
    {
        if (is_array($value)) {
            return $this->setEnvVars($value, "$name.");
        }
        $name = strtoupper(str_replace('.', '_', $name));
        $this->envVars[$name] = $value;
        return $this;
    }

  /**
   * Set an array of environment variables.
   */
    public function setEnvVars(array $vars, string $prefix = '')
    {
        foreach ($vars as $key => $value) {
            if (is_numeric($prefix.$key)) {
                continue;
            }
            $this->setEnvVar($prefix.$key, $value);
        }
        return $this;
    }

  /**
   * {@inheritdoc}
   */
    public function hasEnvVar($name):bool
    {
        return isset($this->envVars[$name]);
    }

  /**
   * Get all the environmental variables.
   */
    public function getEnvVars():array
    {
        return $this->envVars;
    }
}
