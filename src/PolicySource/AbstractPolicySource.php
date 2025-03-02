<?php

namespace Drutiny\PolicySource;

use Drutiny\Attribute\AsSource;
use Drutiny\Helper\TextCleaner;
use Drutiny\LanguageManager;
use Drutiny\Policy;
use Drutiny\PolicySource\Exception\UnknownPolicyException;
use Generator;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use TypeError;

abstract class AbstractPolicySource implements PolicySourceInterface
{
    public readonly string $name;
    public function __construct(
        protected AsSource $source,
        protected CacheInterface $cache
    ) {
        $this->name = $source->name;
    }

    final public function load(array $definition): Policy
    {
        if ($this->source->cacheable) {
            $key = hash('md5', $this->source->name.json_encode($definition));
            return $this->cache->get($key, fn() => $this->doLoad($definition));
        }
        return $this->doLoad($definition);
    }

    protected function doLoad(array $definition): Policy
    {
        try {
            $definition['source'] = $this->source->name;

            $reflection = new ReflectionClass(Policy::class);
            $args = [];

          // Only pass in arguments that the Policy constructor is expecting.
          // This creates backwards compatibility with other drutiny clients
          // reading in newer policy definitions.
            foreach ($reflection->getConstructor()->getParameters() as $arg) {
                if (isset($definition[$arg->name])) {
                    $args[$arg->name] = $definition[$arg->name];
                }
            }
            return new Policy(...$args);
        } catch (TypeError $e) {
            $code = Yaml::dump($args);
            throw new UnknownPolicyException("Cannot load policy '{$definition['name']}' from '{$this->source->name}': " . $e->getMessage() . PHP_EOL . $code, 0, $e);
        }
    }

    final public function getList(LanguageManager $languageManager): array
    {
        $key = TextCleaner::machineValue($this->source->name.'.policy.list.'.$languageManager->getCurrentLanguage());
        if ($this->source->cacheable) {
            return $this->cache->get($key, fn() => $this->doGetList($languageManager));
        }
        return $this->doGetList($languageManager);
    }

    final public function refresh(LanguageManager $languageManager): Generator
    {
        $key = TextCleaner::machineValue($this->source->name.'.policy.list.'.$languageManager->getCurrentLanguage());
        $this->cache->delete($key);
        foreach ($this->getList($languageManager) as $definition) {
            yield $this->load($definition);
        }
    }

    abstract protected function doGetList(LanguageManager $languageManager): array;
}
