<?php

namespace Drutiny\Target;

use Drutiny\Attribute\AsTarget;
use Drutiny\Helper\ProcessUtility;
use Drutiny\Target\Exception\InvalidTargetException;
use Drutiny\Target\Exception\TargetLoadingException;
use Drutiny\Target\Exception\TargetNotFoundException;
use Drutiny\Target\Exception\TargetSourceFailureException;
use Drutiny\Target\Transport\Transport;
use Drutiny\Target\Transport\TransportInterface;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Target for parsing Drush aliases.
 */
#[AsTarget(name: 'ddev')]
class DdevTarget extends DrushTarget implements TargetInterface, TargetSourceInterface
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this['ddev.name'];
    }

    /**
     * @inheritdoc
     * Implements Target::parse().
     */
    public function parse(string $alias, ?string $uri = null): TargetInterface
    {
        $status_cmd = sprintf('ddev describe %s -j', $alias);

        try {
            $ddev = $this->localCommand->run($status_cmd, function ($output) {
                $json = json_decode(trim($output), true);
                return $json['raw'];
            });
        } catch (ProcessFailedException $e) {
            throw new TargetSourceFailureException(message: "DDEV describe command failed to execute: $status_cmd.", previous: $e);
        }

        if (empty($ddev)) {
            throw new TargetNotFoundException(message: "DDEV site '$alias' either doesn't exist or is not currently active.");
        }
        if ($ddev['status'] == 'stopped') {
            throw new TargetLoadingException(message: "DDEV site '$alias' is currently stopped. Please start this service and try again.");
        }

        $ddev['name'] = $alias;
        foreach ($ddev as $k => $v) {
            $this['ddev.'.$k] = $v;
        }

        $docker_compose_bin = getenv('HOME') . '/.ddev/bin/docker-compose';
        if (!file_exists($docker_compose_bin)) {
            throw new InvalidTargetException("Failed to find $docker_compose_bin. Perhaps the wrong version of DDEV is installed?");
        }
        $this['drush.root'] = '/var/www/html/'.$ddev['docroot'];

        $this->transport = Transport::create(function (Process $process, ?callable $processor = null) use ($ddev, $docker_compose_bin) {
            ProcessUtility::mergeEnv($process, $this->localCommand->getEnvVars());
            $command = strtr('%docker_compose_bin -f %approot/.ddev/.ddev-docker-compose-full.yaml exec web bash -c "%command"', [
                '%docker_compose_bin' => $docker_compose_bin,
                '%approot' => $ddev['approot'],
                '%command' => sprintf("echo %s | base64 --decode | sh", base64_encode(ProcessUtility::replacePlaceholders($process)->getCommandLine()))
            ]);
            return $this->localCommand->run(Process::fromShellCommandline($command), $processor);
        });

        // Provide a default URI if none already provided.
        $this->setUri($uri ?? $ddev['primary_url']);
        $this->buildAttributes();
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTransport(): TransportInterface
    {
        $approot = $this['ddev.approot'];

        $docker_compose_bin = getenv('HOME') . '/.ddev/bin/docker-compose';
        if (!file_exists($docker_compose_bin)) {
            throw new InvalidTargetException("Failed to find $docker_compose_bin. Perhaps the wrong version of DDEV is installed?");
        }

        return Transport::create(function (Process $process, ?callable $processor = null) use ($approot, $docker_compose_bin) {
            ProcessUtility::mergeEnv($process, $this->localCommand->getEnvVars());
            $command = strtr('%docker_compose_bin -f %approot/.ddev/.ddev-docker-compose-full.yaml exec web bash -c "%command"', [
                '%docker_compose_bin' => $docker_compose_bin,
                '%approot' => $approot,
                '%command' => sprintf("echo %s | base64 --decode | sh", base64_encode(ProcessUtility::replacePlaceholders($process)->getCommandLine()))
            ]);
            return $this->localCommand->run(Process::fromShellCommandline($command), $processor);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableTargets(): array
    {
        $aliases = $this->localCommand->run('ddev list -A -j', function ($output, CacheItemInterface $cache) {
            $cache->expiresAfter(1);
            $json = json_decode($output, true);
            return array_combine(array_column($json['raw'], 'name'), $json['raw']);
        });

        $targets = [];
        foreach ($aliases as $name => $info) {
            $targets[] = [
            'id' => $name,
            'uri' => $info['primary_url'] ?? '',
            'name' => $name
            ];
        }
        return $targets;
    }
}
