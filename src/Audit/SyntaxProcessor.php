<?php

namespace Drutiny\Audit;

use DateTimeZone;
use Drutiny\Helper\ExpressionLanguageTranslation;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Twig\Error\RuntimeError;

class SyntaxProcessor
{

    public function __construct(
        protected TwigEvaluator $twigEvaluator,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * Process a named parameter.
     *
     * Parameter names starting with a ^ will be interpolated (token replacement).
     * Parameter names starting with a $ will be evaluated (twig rendered).
     * Parameter names starting with an ! will be passed through as static.
     * All other parameters are passed on verbatim (not processed).
     */
    public function processParameter(string $name, mixed $value, array $contexts = []): mixed
    {
        $type = DynamicParameterType::fromParameterName($name);

        // Do not process unspecified types.
        if ($type == DynamicParameterType::NONE) {
            // Traverse arrays for dynamic parameters.
            if (is_array($value)) {
                $new_values = [];
                foreach ($value as $k => $v) {
                    $new_values[$this->processParameterName($k)] = $this->processParameter($k, $v, $contexts);
                }
                return $new_values;
            }
            return $value;
        }

        // Reprocess without the ! prefix. This will traverse arrays but leave strings alone.
        if ($type == DynamicParameterType::STATIC) {
            return $this->processParameter($this->processParameterName($name), $value, $contexts);
        }

        // Cannot process on any other data types other than string and array.
        if (!($is_string = is_string($value)) && (is_array($value) && !array_is_list($value))) {
            throw new InvalidArgumentException("$name must be a string or list to use dynamic parameter processing: " . gettype($value));
        }
        $value = $is_string ? [$value] : $value;
        
        $processed_value = match ($type) {
            DynamicParameterType::REPLACE => array_map(fn ($v) => $this->interpolate($v, $contexts), $value),
            DynamicParameterType::EVALUATE => array_map(fn ($v) => $this->evaluate($v, 'twig', $contexts), $value),
            default => $value
        };

        return $is_string ? reset($processed_value) : $processed_value;
    }

    /**
     * Clean off the processing indicators from the parameter name.
     *
     * @see static::processParameter().
     */
    public function processParameterName(string $name): string
    {
        return DynamicParameterType::fromParameterName($name)->stripParameterName($name);
    }

    /**
     * Process an array of parameters for syntax evaluations.
     */
    public function processParameters(array $parameters, array $contexts = [], ?InputDefinition $definition = null):array
    {
        // Ensure default values are set.
        if (isset($definition)) {
            foreach ($definition->getParameters() as $parameter) {
                $parameters[$parameter->name] ??= $parameter->default;
            }
        }

        $processed_parameters = [];
        $ignored_parameters = [];
        foreach ($parameters as $key => $value) {
            if (in_array($key, $ignored_parameters)) {
                continue;
            }

            $preprocess = DynamicParameterType::fromParameterName($key);

            // If no preprocessing is set, inherit a processor from the parameter definition.
            if (($definition !== null) && $definition->hasParameter($key) && ($preprocess == DynamicParameterType::NONE)) {
                $preprocess = $definition->getParameter($key)->preprocess;
            }

            $name = $this->processParameterName($key);

            if ($name != $key && isset($parameters[$name])) {
                $this->logger->warning("$name already exists and will be overridden by the dynamic parameter: $key.");
            }

            try {
                $processed_parameters[$name] = $this->processParameter(
                    name: $preprocess->decorateParameterName($name),
                    value: $value,
                    contexts: $contexts
                );
            } catch (RuntimeError $e) {
                throw new InvalidArgumentException("Failed to create parameter '$name':\n$value\n" . $e->getMessage(), 0, $e);
            }
            
            $ignored_parameters[] = $name;
        }
        return $processed_parameters;
    }

    /**
     * Evaluate an expression using the Symfony ExpressionLanguage engine.
     */
    public function evaluate(string $expression, $language = 'expression_language', array $contexts = []):mixed
    {
        try {
            if ($language == 'expression_language') {
                $translation = new ExpressionLanguageTranslation($expression);
                $expression = $translation->toTwigSyntax();
            }
            return $this->twigEvaluator->execute($expression, $contexts);
        } catch (\Exception $e) {
            $this->logger->error("Evaluation failure {syntax}: {expression}: {message}", [
                'syntax' => $language,
                'expression' => $expression,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Allow strings to utilise Audit and Target contexts.
     */
    public function interpolate(string $string, array $contexts = []): string
    {
        return $this->_interpolate($string, $contexts);
    }

    public function setTimezone(DateTimeZone $timezone):void
    {
        $this->twigEvaluator->setTimezone($timezone);
    }

    /**
     * Helper function for the public interpolate function.
     */
    private function _interpolate(string $string, iterable $vars, $key_prefix = ''): string
    {
        foreach ($vars as $key => $value) {
            if (is_iterable($value)) {
                $string = $this->_interpolate($string, $value, $key.'.');
            }

            $token = '{'.$key_prefix.$key.'}';
            if (false === strpos($string, $token)) {
                continue;
            }

            $value = (string) $value;
            $string = str_replace($token, $value, $string);
        }

        return $string;
    }
}
