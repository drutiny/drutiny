<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Helper\TextCleaner;
use Drutiny\Policy;
use Drutiny\Policy\Dependency;

/**
 * Check a configuration is set correctly.
 */
#[Parameter(name: 'key', description: 'The name of the variable to get.', type: Type::STRING, mode: Parameter::REQUIRED)]
#[Dependency(expression: 'Drupal.isVersion7')]
class VariableAnalysis extends AbstractAnalysis
{
    private array $vars;
    private array $keys = [];

    /**
     * {@inheritDoc}
     */
    public function prepare(Policy $policy): ?string
    {
        $this->keys[] = $policy->parameters->get('key');
        $this->keys = array_unique($this->keys);
        return __CLASS__;
    }

    #[DataProvider]
    public function variableGet(): void
    {
        $key = $this->getParameter('key');

        if (!isset($this->vars)) {
            $this->vars = $this->target->getService('drush')->variableGet([
                'format' => 'json',
            ])
            ->run(function ($output) {
                $vars = TextCleaner::decodeDirtyJson($output);
                return array_filter($vars, function ($k) {
                    return count(array_filter($this->keys, fn ($key) => strpos($k, $key) === 0));
                },
                ARRAY_FILTER_USE_KEY);
            });
        }

        // Set variables that started with the variable name.
        foreach (array_filter($this->vars, fn($k) => strpos($k, $key) === 0) as $name => $value) {
            $this->set($name, $value);
        }
        // Ensure the variable name is set incase it wasn't present.
        $this->set($key, $this->vars[$key] ?? null);
    }
}
