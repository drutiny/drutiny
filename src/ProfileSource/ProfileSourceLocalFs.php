<?php

namespace Drutiny\ProfileSource;

use Drutiny\Attribute\AsSource;
use Drutiny\LanguageManager;
use Drutiny\Profile;
use Drutiny\ProfileFactory;
use Drutiny\Settings;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSource(name: 'localfs', weight: -10, cacheable: false)]
#[Autoconfigure(tags: ['profile.source'])]
class ProfileSourceLocalFs extends AbstractProfileSource
{
    public function __construct(protected Finder $finder, Settings $settings, AsSource $source, CacheInterface $cache, ProfileFactory $profileFactory)
    {
        parent::__construct(source: $source, cache: $cache, profileFactory: $profileFactory);

        try {
            $dirs = $settings->get('extension.dirs');
            $dirs[] = realpath($settings->get('profile.library.fs'));
            $dirs[] = getcwd() . DIRECTORY_SEPARATOR . $settings->get('profile.library.fs');

            $dirs = array_filter(array_unique($dirs), fn ($f) => $f && is_dir($f));

          // Ensure the profile directory is available.

            $this->finder
              ->files()
              ->depth('<=1')
              ->in($dirs)
              ->name('*.profile.yml');
        } catch (DirectoryNotFoundException $e) {
          // Ignore not finding an existing config dir.
        }
    }

    /**
     * {@inheritdoc}
     */
    public function doGetList(LanguageManager $languageManager):array
    {
        $list = [];
        foreach ($this->finder as $file) {
            $filename = $file->getPathname();
            $name = str_replace('.profile.yml', '', pathinfo($filename, PATHINFO_BASENAME));
            $profile = Yaml::parse($file->getContents());
            $profile['language'] = $profile['language'] ?? $languageManager->getDefaultLanguage();

            if ($languageManager->getCurrentLanguage() != $profile['language']) {
                continue;
            }

            $profile['filepath'] = $filename;
            $profile['name'] = $name;
            unset($profile['format']);
            $list[$name] = $profile;
        }
        return $list;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLoad(array $definition):Profile
    {
        $filepath = $definition['filepath'];

        $info = Yaml::parse(file_get_contents($filepath));
        $info['name'] = str_replace('.profile.yml', '', pathinfo($filepath, PATHINFO_BASENAME));
        $info['uuid'] = $filepath;
        $info['uri'] = $filepath;

        return parent::doLoad($info);
    }
}
