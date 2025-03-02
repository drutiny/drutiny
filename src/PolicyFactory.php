<?php

namespace Drutiny;

use Drutiny\Attribute\AsSource;
use Drutiny\Policy\UnavailablePolicyException;
use Drutiny\Policy\UnknownPolicyException;
use Drutiny\LanguageManager;
use Drutiny\PolicySource\PolicySourceInterface;
use Exception;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Psr\Log\LoggerInterface;
use ReflectionClass;

class PolicyFactory
{

    /**
     * @var \Drutiny\Attribute\AsSource[]
     */
    public readonly array $sources;

    public function __construct(
        protected ContainerInterface $container,
        protected LoggerInterface $logger,
        protected LanguageManager $languageManager,
        protected ProgressBar $progress,
        protected Settings $settings
    ) {
        if (method_exists($logger, 'withName')) {
            $this->logger = $logger->withName('policy.factory');
        }
        $this->sources = $this->buildSources();
    }

    /**
     * Load policy by name.
     *
     * @param $name string
     */
    public function loadPolicyByName($name, ?PolicySourceInterface $source = null):Policy
    {
        $list = $source?->getList($this->languageManager) ?? $this->getPolicyList();

        if (!isset($list[$name])) {
            $list = $this->getPolicyList(true);
            if (!isset($list[$name])) {
                throw new UnknownPolicyException("$name does not exist.");
            }
            throw new UnavailablePolicyException("$name requires {$list[$name]['class']} but is not available in this environment.");
        }
        $definition = $list[$name];
        unset($definition['sources']);

        try {
            return ($source ?? $this->getSource($definition['source']))->load($definition);
        } catch (InvalidArgumentException $e) {
            $this->logger->warning($e->getMessage());
            throw new UnavailablePolicyException("$name requires {$list[$name]['class']} but is not available in this environment.");
        }
    }

    /**
     * Load policies by a keyword search.
     */
    public function getPolicyListByKeyword(string $keyword):array
    {
        $results = [];
        foreach ($this->getPolicyList() as $listedPolicy) {
            $match = strpos(strtolower($listedPolicy['title']), $keyword) !== false;
            $match = $match || (strpos(strtolower($listedPolicy['name']), $keyword) !== false);
            $match = $match || in_array($keyword, $listedPolicy['tags'] ?? []);
            if (!$match) {
                continue;
            }
            $results[] = $listedPolicy;
        }
        return $results;
    }

    /**
     * Acquire a list of available policies.
     *
     * @return array[] of policy information arrays.
     */
    public function getPolicyList($include_invalid = false)
    {
        static $policy_list, $available_list;

        if ($include_invalid && !empty($policy_list)) {
            return $policy_list;
        }

        if (!empty($available_list)) {
            return $available_list;
        }

        $policy_list = [];
        // Add steps to the progress bar.
        $this->progress->setMaxSteps($this->progress->getMaxSteps() + count($this->sources));
        foreach ($this->sources as $ref) {
            $source = $this->getSource($ref->name);

            try {
                $items = $source->getList($this->languageManager);
                $this->logger->notice($ref->name . " has " . count($items) . " polices.");
                foreach ($items as $name => $item) {
                    $item['source'] = $ref->name;

                    if (isset($policy_list[$name])) {
                        $item['sources'] = $policy_list[$name]['sources'] ?? [];
                    }
                    $item['sources'][] = $item['source'];
                    $policy_list[$name] = $item;
                }
            } catch (\Exception $e) {
                $this->logger->error(strtr("Failed to load policies from source: @name: @error", [
                '@name' => $ref->name,
                '@error' => $e->getMessage(),
                ]));
            }
            $this->progress->advance();
        }

        if ($include_invalid) {
            return $policy_list;
        }

        $available_list = array_filter($policy_list, function ($listedPolicy) {
            if (!class_exists($listedPolicy['class'])) {
                $this->logger->debug('Failed to find class:  ' . $listedPolicy['class']);
                return false;
            }
            return true;
        });
        return $available_list;
    }

    /**
     * Load the policies from a single source.
     */
    public function getSourcePolicyList(string $source): array
    {
        return $this->getSource($source)->getList($this->languageManager);
    }

    /**
     * Load the sources that provide policies.
     *
     * @return \Drutiny\PolicySource\PolicySourceInterface[]
     */
    private function buildSources():array
    {
        $sources = [];
        foreach ($this->settings->get('policy.source.registry') as $name => $class) {
            $reflectionAttributes = (new ReflectionClass($class))->getAttributes(AsSource::class);
            if (empty($reflectionAttributes)) {
                throw new Exception("PolicySource '$name' is missing the AsSource attribute.");
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
    public function getSource(string $name): PolicySourceInterface
    {
        $registry = $this->settings->get('policy.source.registry');
        if (!isset($registry[$name])) {
            throw new Exception("Policy source '$name' does not exist.");
        }
        return $this->container->get($registry[$name]);
    }
}
