<?php

namespace Drutiny\PolicySource;

use Drutiny\Attribute\AsSource;
use Drutiny\LanguageManager;
use Drutiny\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSource(name: 'localfs', weight: -10, cacheable: false)]
#[Autoconfigure(tags: ['policy.source'])]
class LocalFs extends AbstractPolicySource
{
    public function __construct(
        protected Finder $finder,
        protected Settings $settings,
        protected LoggerInterface $logger,
        CacheInterface $cache,
        AsSource $source
    ) {
        parent::__construct(cache: $cache, source: $source);

        $dirs = $settings->get('extension.dirs');
        $dirs[] = realpath($settings->get('policy.library.fs'));
        $dirs[] = getcwd() . DIRECTORY_SEPARATOR . $settings->get('policy.library.fs');

        $dirs = array_filter(array_unique($dirs), fn ($f) => $f && is_dir($f));

        // Ensure the policy directory is available.

        $this->finder
            ->files()
            ->depth('<=1')
            ->in($dirs)
            ->name('*.policy.yml');
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetList(LanguageManager $languageManager):array
    {
        $list = [];
        foreach ($this->finder as $file) {
            try {
                $policy = Yaml::parse($file->getContents());
            } catch (ParseException $e) {
                $this->logger->error("YAML Parse error in " . $file . ": " . $e->getMessage());
                continue;
            }
            $policy['uuid'] = md5($file->getPathname());
            $policy['uri'] = (string) $file;
            $policy['language'] = $policy['language'] ?? $languageManager->getDefaultLanguage();

            if ($policy['language'] != $languageManager->getCurrentLanguage()) {
                continue;
            }
            if (isset($list[$policy['name']])) {
                $this->logger->warning("Policy {$policy['name']} already exists at {$list[$policy['name']]['uri']}. Policy at URL will be ignored: {$policy['uri']}.");
            }
            // If it already exists, take the first entry over the latter.
            $list[$policy['name']] ??= $policy;
        }
        return $list;
    }
}
