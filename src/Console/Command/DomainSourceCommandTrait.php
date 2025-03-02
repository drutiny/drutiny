<?php

namespace Drutiny\Console\Command;

use Drutiny\Report\FormatInterface;
use Drutiny\DomainSource;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;

/**
 *
 */
trait DomainSourceCommandTrait
{
  /**
   * @inheritdoc
   */
    protected function configureDomainSource(DomainSource $domainSource)
    {
        $this
        ->addOption(
            'domain-source',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Use a domain source to preload uri options. Defaults to yaml filepath.',
            'yaml'
        )->addOption(
            'domain-source-blacklist',
            null,
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Exclude domains that match this regex filter',
            []
        )
        ->addOption(
            'domain-source-whitelist',
            null,
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Exclude domains that don\'t match this regex filter',
            []
        );

        // Build a way for the command line to specify the options to derive
        // domains from their sources.
        foreach ($domainSource->getAllInputOptions() as $option) {
            $this->addOption(
                'domain-source-' . $option->getName(),
                null,
                $domainSource->getInputOptionMode($option),
                $option->getDescription()
            );
        }
    }

    protected function parseDomainSourceOptions(InputInterface $input):array
    {
      // Load additional uris from domain-source
        $sources = [];
        foreach ($input->getOptions() as $name => $value) {
            if ($value === null) {
                continue;
            }
            if (strpos($name, 'domain-source-') === false) {
                continue;
            }
            $param = str_replace('domain-source-', '', $name);
            if (strpos($param, '-') === false) {
                continue;
            }
            list($source, $name) = explode('-', $param, 2);
            $sources[$source][$name] = $value;
        }
        return $sources;
    }

    /**
     * Determine a default filepath.
     */
    protected function getDomainSource():DomainSource
    {
        throw new \Exception("Unsupported method. Please use dependency injection for class: " . DomainSource::class);
    }
}
