<?php

namespace Drutiny\Audit;

class TwigEvaluatorFunction
{
    public function __construct(
        public readonly string $description,
        public readonly ?string $expression = null,
        public readonly ?string $use_audit = null,
        public readonly array $arguments = [],
        public readonly string $return = 'bool',
        public readonly array $depends = [],
        public readonly mixed $default = null
    ) {
    }

    public function returnValue(mixed $value):mixed
    {
        return match ($this->return) {
            'bool' => (bool) $value,
            'array' => (array) $value,
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'object' => (object) $value,
            default => $value
        };
    }

    /**
     * Argument Spec
     *
     * The name of an argument to pass into an expression:
     * - arg1
     *
     * The token in an expression to swap out with a literal value:
     * - $arg1
     *
     * A parameter to map into a policy using use_audit:
     * $arg1: arg1
     *
     * This allows us to pass in variables from the twig runtime into function
     * provided the dependency definition specifies which keys to map the order
     * of arguments passed into the function.
     */
    public function buildContexts(array $args = []):array
    {
        $contexts = [];
        foreach ($this->arguments as $param) {
            $contexts[$param] = array_shift($args);
        }
        return $contexts;
    }
}
