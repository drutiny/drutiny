<?php

namespace Drutiny;

use Drutiny\Attribute\AsSource;
use Drutiny\ProfileSource\ProfileSourceInterface;
use Drutiny\LanguageManager;
use Drutiny\Profile\ProfileNotFoundException;
use Drutiny\ProfileSource\ProfileCompilationException;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class ProfileFactory
{
    public readonly array $sources;

    public function __construct(
        protected ContainerInterface $container,
        protected CacheInterface $cache,
        protected LanguageManager $languageManager,
        protected ProgressBar $progress,
        protected Settings $settings,
        protected LoggerInterface $logger
    ) {
        $this->sources = $this->buildSources();
    }

    /**
     * Create a profile from an array of values.
     */
    public function create(array $values):Profile
    {
        $includes = $values['include'] ?? [];
        unset($values['include']);
        $profile = new Profile(...$values);
        try {
            foreach ($includes as $include) {
                $profile = $profile->mergeWith($this->loadProfileByName($include));
            }
        } catch (ProfileNotFoundException $e) {
            throw new ProfileCompilationException("Cannot initialize profile '{$profile->name}' due to a profile include '$include' failing to load.", 0, $e);
        }
        return $profile;
    }

    /**
     * Load policy by name.
     */
    public function loadProfileByName(string|Profile $name, ?ProfileSourceInterface $source = null):Profile
    {
        if ($name instanceof Profile) {
            return $name;
        }

        $list = $this->getProfileList();

        if (!isset($list[$name])) {
            throw new ProfileNotFoundException("No such profile found: '$name'. Available: " . implode(', ', array_keys($list)));
        }
        $definition = $list[$name];
        return ($source ?? $this->getSource($definition['source']))->load($definition);
    }

  /**
   * Acquire a list of available policies.
   *
   * @return array of policy information arrays.
   */
    public function getProfileList():array
    {
        $list = [];
        $this->progress->setMaxSteps($this->progress->getMaxSteps() + count($this->sources));
        foreach ($this->sources as $ref) {
            $source = $this->getSource($ref->name);
            foreach ($source->getList($this->languageManager) as $name => $item) {
                $item['source'] = $ref->name;
                $list[$name] = $item;
            }
            $this->progress->advance();
        }
        $allow_list = $this->settings->has('profile.allow_list') ? $this->settings->get('profile.allow_list') : [];
        return array_filter($list, fn($p) => empty($allow_list) || in_array($p, $allow_list), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Load the sources that provide profiles.
     */
    private function buildSources():array
    {
        $sources = [];
        foreach ($this->settings->get('profile.source.registry') as $name => $class) {
            $reflectionAttributes = (new ReflectionClass($class))->getAttributes(AsSource::class);
            if (empty($reflectionAttributes)) {
                throw new Exception("ProfileSource '$name' ($class) is missing the AsSource attribute.");
            }
            $sources[$class] = $reflectionAttributes[0]->newInstance();
        }

        usort($sources, function ($a, $b) {
            if ($a->weight == $b->weight) {
                return 0;
            }
            return $a->weight > $b->weight ? 1 : -1;
        });

        return $sources;
    }

  /**
   * Load a single source.
   */
    public function getSource($name):ProfileSourceInterface
    {
        $reg = $this->settings->get('profile.source.registry');
        if (!isset($reg[$name])) {
            throw new \Exception("No such source found: $name.");
        }
        return $this->container->get($reg[$name]);
    }
}
