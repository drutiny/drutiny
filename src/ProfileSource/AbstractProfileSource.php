<?php

namespace Drutiny\ProfileSource;

use Drutiny\Attribute\AsSource;
use Drutiny\Helper\TextCleaner;
use Drutiny\LanguageManager;
use Drutiny\Profile;
use Drutiny\ProfileFactory;
use Generator;
use Symfony\Contracts\Cache\CacheInterface;
use TypeError;

abstract class AbstractProfileSource implements ProfileSourceInterface
{
    public function __construct(protected AsSource $source, protected CacheInterface $cache, protected ProfileFactory $profileFactory)
    {
    }

    final public function load(array $definition): Profile
    {
        $key = hash('md5', $this->source->name.json_encode($definition));
        if ($this->source->cacheable) {
            return $this->cache->get($key, fn() => $this->doLoad($definition));
        }
        return $this->doLoad($definition);
    }

    protected function doLoad(array $definition): Profile
    {
        try {
            $definition['source'] = $this->source->name;
            return $this->profileFactory->create($definition);
        } catch (TypeError $e) {
            throw new ProfileCompilationException("Cannot create {$definition['name']} from {$this->source->name}: " . $e->getMessage(), 0, $e);
        }
    }

    final public function getList(LanguageManager $languageManager): array
    {
        $key = TextCleaner::machineValue($this->source->name.'.profile.list.'.$languageManager->getCurrentLanguage());
        if ($this->source->cacheable) {
            return $this->cache->get($key, fn() => $this->doGetList($languageManager));
        }
        return $this->doGetList($languageManager);
    }

    final public function refresh(LanguageManager $languageManager): Generator
    {
        $key = TextCleaner::machineValue($this->source->name.'.profile.list.'.$languageManager->getCurrentLanguage());
        foreach ($this->getList($languageManager) as $definition) {
            yield $this->load($definition);
        }
    }

    abstract protected function doGetList(LanguageManager $languageManager): array;
}
