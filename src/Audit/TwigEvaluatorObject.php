<?php

namespace Drutiny\Audit;

use Drutiny\AuditFactory;
use Drutiny\Policy;
use Drutiny\Target\TargetInterface;
use Exception;

class TwigEvaluatorObject
{
    public function __construct(
        public readonly string $namespace,
        protected array $set,
        protected TwigEvaluator $twigEvaluator,
        protected AuditFactory $auditFactory
    ) {
    }

        /**
         * Allow argument-less methods to be called as properties.
         */
    public function __get($name):mixed
    {
        return $this->__call($name, []);
    }

    public function __call($name, $args):mixed
    {
        if (!isset($this->set[$name])) {
            throw new Exception("{$this->namespace}.{$name} does not exist.");
        }
        $function = new TwigEvaluatorFunction(...$this->set[$name]);

        $contexts = $function->buildContexts($args);

        $target = $this->twigEvaluator->getGlobalContexts()['target'] ?? throw new Exception("Missing target from twigEvaluator global contexts.");

        // Ensure dependencies are met before running evaluation.
        $vars = array_filter(array_keys($contexts), fn($key) => strpos($key, '$') === 0);
        foreach ($function->depends as $expression) {
            foreach ($vars as $key) {
                $expression = str_replace($key, '\'' . $contexts[$key] . '\'', $expression);
            }
            if (!$this->twigEvaluator->execute($expression, $contexts)) {
                return $function->returnValue($function->default);
            }
        }

        if (isset($function->use_audit)) {
            $return = $this->runPolicyAudit($name, $function, $target, $contexts);
            if ($function->return == 'bool') {
                return $return;
            }
            foreach ($return as $key => $value) {
                $contexts[$key] = $value;
            }
        }
        if (!isset($function->expression)) {
            throw new \Exception("Invalid dependency {$this->namespace}.$name. Requires 'expression'.");
        }
            
        // Dependencies primarily need to evaluate the target so we'll export
        // the target properties into the contexts so they're more accessible.
        foreach ($target->getPropertyList() as $key) {
            $contexts[$key] = $target->getProperty($key);
        }

        return $function->returnValue($this->twigEvaluator->execute($function->expression, $contexts));
    }

        /**
         * Create a pseudo policy to audit a result from.
         */
    protected function runPolicyAudit(string $name, TwigEvaluatorFunction $function, TargetInterface $target, array $contexts):mixed
    {
        $audit = $this->auditFactory->mock($function->use_audit, $target);
        $audit->setReportingPeriod(new \DateTime, new \DateTime);
        $tokens = [];
        foreach ($contexts as $key => $value) {
            // Cannot use as policy parameters.
            if (!$audit->getDefinition()->hasParameter($key)) {
                // Assume these are tokens to swap out in the expression.
                $tokens[$key] = match (true) {
                    strtolower($value) == 'true' => 'true',
                    strtolower($value) == 'false' => 'false',
                    strtolower($value) == 'null' => 'null',
                    is_numeric($value) => $value,
                    // String
                    default => "'$value'"
                };
                    unset($contexts[$key]);
            }
        }

        if ($audit->getDefinition()->hasParameter('expression') && isset($function->expression) && $function->return == 'bool') {
            $contexts['expression'] = strtr($function->expression, $tokens);
            $contexts['syntax'] = 'twig';
        }

        // Set required fields.
        $policy = new Policy(
            uuid: $this->namespace.$name,
            name: $this->namespace.':'.$name,
            title: "{$this->namespace}.$name",
            description: $name,
            failure: 'Failure message',
            success: 'Success message',
            class: $function->use_audit,
            parameters: $contexts,
            source: 'phpunit'
        );
        return match ($function->return) {
            'bool' => $audit->execute($policy)->state->isSuccessful(),
            default => $function->returnValue($audit->execute($policy)->tokens)
        };
    }
}
