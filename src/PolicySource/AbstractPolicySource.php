<?php

namespace Drutiny\PolicySource;

use Drutiny\Attribute\AsSource;
use Drutiny\Helper\TextCleaner;
use Drutiny\LanguageManager;
use Drutiny\Policy;
use Drutiny\PolicySource\Exception\UnknownPolicyException;
use Generator;
use Symfony\Contracts\Cache\CacheInterface;
use TypeError;

abstract class AbstractPolicySource implements PolicySourceInterface {
    public readonly string $name;
    public function __construct(
        protected AsSource $source,
        protected CacheInterface $cache
    )
    {
        $this->name = $source->name;
    }

    final public function load(array $definition): Policy
    {
        $key = hash('md5', $this->source->name.json_encode($definition));
        return $this->cache->get($key, fn() => $this->doLoad($definition));
    }

    protected function doLoad(array $definition): Policy
    {
      try {
        $definition['source'] = $this->source->name;
        return new Policy(...$definition);
      }
      catch (TypeError $e) {
        throw new UnknownPolicyException("Cannot load policy '{$definition['name']}' from '{$this->source->name}': " . $e->getMessage(), 0, $e);
      }
    }

    final public function getList(LanguageManager $languageManager): array
    {
        $key = TextCleaner::machineValue($this->source->name.'.policy.list.'.$languageManager->getCurrentLanguage());
        return $this->cache->get($key, fn() => $this->doGetList($languageManager));
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