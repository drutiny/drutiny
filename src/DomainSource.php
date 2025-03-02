<?php

namespace Drutiny;

use Drutiny\DomainList\DomainListInterface;
use Drutiny\Target\TargetInterface;
use Generator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;

class DomainSource
{
    public function __construct(
        protected Settings $settings,
        protected ContainerInterface $container,
        protected CacheInterface $cache
    ) {
    }

    /**
     * Get a DomainListInterface object from the service container.
     */
    protected function getServiceFromName(string $name):DomainListInterface
    {
        $registry = $this->settings->get('domain_list.registry');
        if (!isset($registry[$name])) {
            throw new InvalidArgumentException("No such DomainList source exists: $name.");
        }
        return $this->container->get($registry[$name]);
    }

    /**
     * Iterator for working with DomainSourceInterface lists.
     */
    public function getAll():Generator
    {
        foreach ($this->settings->get('domain_list.registry') as $name => $id) {
            yield $name => $this->container->get($id);
        }
    }

    public function getAllInputOptions():Generator
    {
        foreach ($this->getAll() as $name => $domainSource) {
            foreach ($domainSource->getInputOptions() as $inputOption) {
                // Wrapper function ensures input is an InputOption.
                $inputOption =  (fn(InputOption $i) => $i)($inputOption);
                
                yield new InputOption(
                    name: $name.'-'.$inputOption->getName(),
                    description: $inputOption->getDescription(),
                    mode: $this->getInputOptionMode($inputOption)
                );
            }
        }
    }

    /**
     * Get the InputOption mode from an InputOption instance.
     */
    public function getInputOptionMode(InputOption $inputOption):int
    {
        // VALUE_REQUIRED is not a supported mode.
        $mode = $inputOption->acceptValue() ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_NONE;
        if ($inputOption->isNegatable()) {
            $mode = $mode | InputOption::VALUE_NEGATABLE;
        }
        if ($inputOption->isArray()) {
            $mode = $mode | InputOption::VALUE_IS_ARRAY;
        }
        return $mode;
    }

    /**
     * @deprecated use getAll() instead.
     */
    public function getSources():array
    {
        return $this->cache->get('domain_list.sources', function ($item) {
            $sources = [];
            foreach ($this->settings->get('domain_list.registry') as $name => $id) {
                $sources[$name] = $this->container->get($id)->getOptionsDefinitions();
            }
            return $sources;
        });
    }

    /**
     * Get a list of domains from a given source provider.
     */
    public function getDomains(TargetInterface $target, string $source, array $options = []): array
    {
        return $this->getServiceFromName($source)->getDomains($target, $options);
    }

    public function loadFromInput(InputInterface $input, TargetInterface $target)
    {
        $sources = [];
        foreach ($input->getOptions() as $name => $value) {
            if (strpos($name, 'domain-source-') === false) {
                continue;
            }
            list($source, $name) = explode('-', str_replace('domain-source-', '', $name), 2);
            $sources[$source][$name] = $value;
        }

        $domains = [];

        foreach ($sources as $source => $options) {
            $domains += $this->getDomains($target, $source, $options);
        }

        $whitelist = $input->getOption('domain-source-whitelist');
        $blacklist = $input->getOption('domain-source-blacklist');

      // Filter domains by whitelist and blacklist.
        return array_filter($domains, function ($domain) use ($whitelist, $blacklist) {
          // Whitelist priority.
            if (!empty($whitelist)) {
                foreach ($whitelist as $regex) {
                    if (preg_match("/$regex/", $domain)) {
                        return true;
                    }
                }
              // Did not pass the whitelist.
                return false;
            }
            if (!empty($blacklist)) {
                foreach ($blacklist as $regex) {
                    if (preg_match("/$regex/", $domain)) {
                        return false;
                    }
                }
            }
            return true;
        });
    }
}
