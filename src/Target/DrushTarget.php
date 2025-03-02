<?php

namespace Drutiny\Target;

use Drutiny\Attribute\AsTarget;
use Drutiny\Helper\TextCleaner;
use Drutiny\Target\Exception\TargetLoadingException;
use Drutiny\Target\Exception\TargetNotFoundException;
use Drutiny\Target\Exception\TargetSourceFailureException;
use Drutiny\Target\Service\Drush;
use Drutiny\Target\Service\ServiceInterface;
use Drutiny\Target\Transport\SshTransport;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Target for parsing Drush aliases.
 */
#[AsTarget(name: 'drush')]
class DrushTarget extends Target implements
    TargetInterface,
    TargetSourceInterface,
    DrushTargetInterface,
    FilesystemInterface
{

    protected bool $hasBuilt = false;

  /**
   * {@inheritdoc}
   */
    public function getId():string
    {
        return $this['drush.alias'];
    }

  /**
   * @inheritdoc
   * Implements Target::parse().
   */
    public function parse(string $alias, ?string $uri = null):TargetInterface
    {
        $this['drush.alias'] = $alias;

        try {
            $status_cmd = Process::fromShellCommandline('drush site:alias $DRUSH_ALIAS --format=json');
            $drush_properties = $this->localCommand->run($status_cmd, function ($output, CacheItemInterface $cache) use ($alias) {
                $cache->expiresAfter(1);
                $json = TextCleaner::decodeDirtyJson($output);
                $index = substr($alias, 1);
                return $json[$index] ?? $json[$alias] ?? array_shift($json);
            });
        } catch (ProcessFailedException $e) {
            throw match ($e->getProcess()->getExitCode()) {
                130 => new TargetNotFoundException(message: "Drush alias $alias not found.", previous: $e),
                default => new TargetSourceFailureException(message: $e->getMessage(), previous: $e)
            };
        }

        $this['drush']->add($drush_properties);

        $this->parseDrushSshOptions();

        // Provide a default URI if none already provided.
        if ($uri) {
            parent::setUri($uri);
        } elseif (isset($drush_properties['uri']) && !$this->hasProperty('uri')) {
            parent::setUri($drush_properties['uri']);
        }
        $this->rebuildEnvVars();
        $this->buildAttributes();
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureService(ServiceInterface $service):void
    {
        if (!$url = $this->getUri()) {
            return;
        }
        if ($service instanceof Drush) {
            $service->setUrl($url);
        }
    }

    /**
     * Decorate target with drush status and php information.
     */
    protected function buildAttributes():DrushTarget
    {
        $this->hasBuilt = true;

        /* @var Drutiny\Target\Service\Drush */
        $service = $this->getService('drush');

        try {
            $status = $service->status(['format' => 'json'])->run(function (Process $process) {
                $output = $process->getOutput();
                return TextCleaner::decodeDirtyJson($output) ?? [];
            });

            foreach ($status as $key => $value) {
                $this['drush.'.$key] = $value;
            }

            $version = $this->execute(Process::fromShellCommandline('php -v | head -1 | awk \'{print $2}\''));
            $this['php_version'] = trim($version);

            return $this;
        } catch (ProcessFailedException $e) {
            throw new TargetLoadingException(message: $e->getMessage(), previous: $e);
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setUri(string $uri):TargetInterface
    {
        parent::setUri($uri);

      // Rebuild the drush attributes if they've been built already.
        if ($this->hasBuilt) {
            $this->buildAttributes();
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDirectory():string
    {
        if (!$this->hasBuilt) {
            $this->buildAttributes();
        }
        return $this['drush.root'];
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableTargets():array
    {
        $aliases = $this->localCommand->run(Process::fromShellCommandline('drush site:alias --format=json'), function ($output, CacheItemInterface $cache) {
            $cache->expiresAfter(1);
            return TextCleaner::decodeDirtyJson($output);
        });

        if (empty($aliases)) {
            $this->logger->error("Drush failed to return any aliases. Please ensure your local drush is up to date, has aliases available and returns a valid json response for `drush site:alias --format=json`.");
            return [];
        }

        $valid = array_filter(array_keys($aliases), function ($a) {
            return strpos($a, '.') !== false;
        });

        $targets = [];
        foreach ($valid as $name) {
            $alias = $aliases[$name];
            $targets[] = [
            'id' => $name,
            'uri' => $alias['uri'] ?? '',
            'name' => $name
            ];
        }
        return $targets;
    }
  
    /**
     * Parse SSH data from the drush alias.
     */
    protected function parseDrushSshOptions():void
    {
      // Check for indicators the drush site uses SSH to access the site.
        if (!$this->hasProperty('drush.remote-host') && !$this->hasProperty('drush.host')) {
            return;
        }

      // Drush 10 and later omits the 'remote-' part.
        $host = $this->hasProperty('drush.host') ? $this['drush.host'] : $this['drush.remote-host'];
        $user = $this->hasProperty('drush.user') ? $this['drush.user'] : ($this->hasProperty('drush.remote-user') ? $this['drush.remote-user'] : null);

        $this->transport = new SshTransport($this->localCommand);
        $this->transport->setConfig('Host', $host);
        if (isset($user)) {
            $this->transport->setConfig('User', $user);
        }

        if (!$this->hasProperty('drush.ssh-options')) {
            return;
        }

        $options = $this['drush.ssh-options'];
      // Port parsing.
        if (preg_match('/-p (\d+)/', $options, $matches)) {
            $this->transport->setConfig('Port', $matches[1]);
        }
      // IdentifyFile
        if (preg_match('/-i ([^ ]+)/', $options, $matches)) {
            $this->transport->setConfig('IdentityFile', $matches[1]);
        }
        if (preg_match_all('/-o "([^ "]+) ([^"]+)"/', $options, $matches)) {
            foreach ($matches[1] as $idx => $key) {
                $this->transport->setConfig($key, $matches[2][$idx]);
            }
        }
        if (preg_match_all('/-o ([^=]+)=([^ ]+)/', $options, $matches)) {
            foreach ($matches[1] as $idx => $key) {
                $this->transport->setConfig($key, $matches[2][$idx]);
            }
        }
    }
}
