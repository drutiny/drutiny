<?php

namespace Drutiny\Audit;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Attribute\Version;
use Drutiny\Audit;
use Drutiny\AuditResponse\State;
use Drutiny\Entity\DataBag;
use Drutiny\Policy;
use Drutiny\Policy\Severity;
use Drutiny\Sandbox\Sandbox;
use InvalidArgumentException;
use ReflectionClass;
use Twig\Error\RuntimeError;

/**
 * Audit gathered data.
 */
#[Parameter(name: 'expression', type: Type::STRING, description: 'Twig syntax to evaluate audit data into an outcome.', default: 'true')]
#[Parameter(name: 'variables', type: Type::HASH, description: 'A keyed array of expressions to set variables before evaluating the passing expression.', default: [])]
#[Parameter(name: 'syntax', type: Type::STRING, enums: ['twig', 'expression_language'], default: 'expression_language', description: 'Which syntax to use. Options: twig or expression_language.')]
#[Parameter(name: 'not_applicable', type: Type::STRING, default: 'false', description: 'An expression that if returns true, will render the policy as not applicable.')]
#[Parameter(name: 'fail_on_error', type: Type::BOOLEAN, default: false, description: 'Set to true if you want an error during data gathering to result in a failure.')]
#[Parameter(name: 'omitIf', type: Type::STRING, description: 'Omit policy if twig expression returns true after data is gathered but before the variables parameter is rendered.')]
#[Parameter(name: 'failIf', type: Type::STRING, description: 'Fail policy if twig expression returns true')]
#[Parameter(name: 'warningIf', type: Type::STRING, description: 'Add warning to outcome if expression return true')]
#[Parameter(
    name: 'severityCriticalIf',
    type: Type::STRING,
    description: 'Change the policy severity to Critical if policy outcome is a failure and this expression is true.'
)]
#[Parameter(
    name: 'severityHighIf',
    type: Type::STRING,
    description: 'Change the policy severity to High if policy outcome is a failure and this expression is true. Cannot lower severity if severity is higher.'
)]
#[Parameter(
    name: 'severityNormalIf',
    type: Type::STRING,
    description: 'Change the policy severity to Normal if policy outcome is a failure and this expression is true. Cannot lower severity if severity is higher.'
)]
/**
 * Changelog
 *
 * 2.1: Added Policy.omits to the expression reference.
 */
#[Version('2.1', compatibilty: '^2.0')]
class AbstractAnalysis extends Audit
{

    public function prepare(Policy $policy): ?string
    {
      // If the policy is using this class directly
      // then the policy can be batched with others.
      // Set the class as the batch ID.
        return $policy->class == AbstractAnalysis::class ?
        AbstractAnalysis::class : $policy->class;
    }

    /**
     * Call the 'gather' method with injected arguments.
     */
    private function doGather(Sandbox $sandbox):void
    {
        $reflection = new ReflectionClass($this);

      // Collect methods with the DataProvider attribute.
        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes(DataProvider::class) as $attr) {
                $methods[] = [
                'weight' => $attr->newInstance()->weight,
                'method' => $method
                ];
                break;
            }
        }

      // Backwards compatibility. Use the gather method if DataProvider attributes
      // were not provided.
        if (empty($methods) && !$reflection->hasMethod('gather')) {
            return;
        } elseif (empty($methods)) {
            $methods[] = ['weight' => 0, 'method' => $reflection->getMethod('gather')];
        }

      // Sort the methods. Lighter weight methods go to the top.
        usort($methods, function ($a, $b) {
            if ($a['weight'] == $b['weight']) {
                return 0;
            }
            return $a['weight'] > $b['weight'] ? 1 : -1;
        });

      // Map to just methods.
        $methods = array_map(fn($a) => $a['method'], $methods);

        foreach ($methods as $method) {
            $args = [];
            foreach ($method->getParameters() as $parameter) {
                $name = $parameter->getName();
                if (!$parameter->hasType()) {
                    throw new AuditValidationException("method 'gather' argument '$name' requires type-hinting to have value injected.");
                }
                $type = (string) $parameter->getType();
  
              // Backwards compatibilty. Support gather methods that
              // define the Sandbox argument.
                if ($type == Sandbox::class) {
                    $args[$name] = $sandbox;
                } else {
                    $args[$name] = $this->container->get($type);
                }
            }
            $method->invoke($this, ...$args);
        }
    }

    /**
     * Expose the data gathering as a public function.
     *
     * This is so that data can be used by aggregate audit classes.
     */
    final public function getGatheredData(array $parameters, Sandbox $sandbox):DataBag
    {
        $parameters = $this->syntaxProcessor->processParameters($parameters, $this->getContexts(), $this->getDefinition());
        $values = $this->getDefinition()->fromValues($parameters);
        $this->dataBag->get('parameters')->add($values);
        $this->dataBag->add($values);
        $this->doGather($sandbox);
        $this->processVariables();
        return $this->dataBag;
    }

    private function processVariables(): void
    {
        foreach ($this->getParameter('variables', []) as $key => $value) {
            try {
              // Allow variables to be processed with process indicators on keys.
                if (DynamicParameterType::fromParameterName($key) != DynamicParameterType::NONE) {
                    $this->set(
                        name: DynamicParameterType::fromParameterName($key)->stripParameterName($key),
                        value: $this->syntaxProcessor->processParameter($key, $value, $this->getContexts())
                    );
                }
              // Otherwise use fallback method of processing with twig.
                else {
                    $this->set($key, $this->evaluate($value, 'twig'));
                }
            } catch (RuntimeError $e) {
                $keys = array_keys($this->getContexts());
                throw new \Exception(get_class($this) . ": Failed to create variable: $key. Encountered Twig runtime error: " . $e->getMessage() . "\n Available keys: " . implode(', ', $keys), 0, $e);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function audit(Sandbox $sandbox)
    {
        try {
            $this->doGather($sandbox);
        } catch (\Exception $e) {
            if ($this->getParameter('fail_on_error')) {
                $this->set('exception', $e->getMessage());
                $this->set('exception_type', get_class($e));
                return self::FAIL;
            }
          // Continue the error as normal.
            throw $e;
        }

        $syntax = $this->getParameter('syntax', 'expression_language');

        if ($expression = $this->getParameter('not_applicable', 'false')) {
            $this->logger->debug(__CLASS__ . ':INAPPLICABILITY ' . $expression);
            if ($this->evaluate($expression, $syntax)) {
                return State::NOT_APPLICABLE->value;
            }
        }
        $omitIf = $this->getParameter('omitIf');

        try {
            if ($omitIf !== null && $this->evaluate($omitIf, 'twig')) {
                $this->logger->debug(__CLASS__ . ':EXPRESSION(omitIf): ' . $omitIf);
                return State::IRRELEVANT->value;
            }
        } catch (RuntimeError $e) {
            throw new InvalidArgumentException("Could not determine outcome of 'omitIf' statement due to a Twig Runtime error. Note: variables from the `variables` parameter have not been evaluated yet.", 0, $e);
        }

        $this->processVariables();

        // Set outcome to failure if failIf expression is true.
        // Note: This takes precedence of the `expression` parameter which does not get evaluated
        // if this parameter is set.
        $failIf = $this->getParameter('failIf');
        if ($failIf !== null) {
            $this->logger->debug(__CLASS__ . ':EXPRESSION(failIf): ' . $failIf);
            $outcome = $this->evaluate($failIf, 'twig') ? State::FAILURE : State::SUCCESS;
        }
        if (!isset($outcome) || $outcome === State::SUCCESS) {
            $expression = $this->getParameter('expression', 'true');
            $this->logger->debug(__CLASS__ . ':EXPRESSION: ' . $expression);
            $outcome = State::fromValue($this->evaluate($expression, $syntax));
        }

        // Add a warning if warningIf expression is true.
        if (($outcome->isSuccessful() || $outcome->isFailure()) && !$outcome->hasWarning()) {
            $warningIf = $this->getParameter('warningIf');
            if ($warningIf !== null) {
                $this->logger->debug(__CLASS__ . ':EXPRESSION(warningIf): ' . $warningIf);
                $outcome = $this->evaluate($warningIf, 'twig') ? $outcome->withWarning() : $outcome;
            }
        }

        // Manipulate policy severity.
        if ($outcome->isFailure()) {
            $this->setPolicySeverity();
        }

        return $outcome->value;
    }

    /**
     * Increase the severity of a policy based on an expression provided in the policy.
     */
    private function setPolicySeverity():void
    {
        $weight = $this->policy->severity->getWeight();
        $expression = $this->getParameter('severityNormalIf');
        if ($weight < Severity::NORMAL->getWeight() && $expression !== null && $this->evaluate($expression, 'twig')) {
            $this->policy = $this->policy->with(severity: Severity::NORMAL);
        }
        $expression = $this->getParameter('severityHighIf');
        if ($weight < Severity::HIGH->getWeight() && $expression !== null && $this->evaluate($expression, 'twig')) {
            $this->policy = $this->policy->with(severity: Severity::HIGH);
        }
        $expression = $this->getParameter('severityCriticalIf');
        if ($weight < Severity::CRITICAL->getWeight() && $expression !== null && $this->evaluate($expression, 'twig')) {
            $this->policy = $this->policy->with(severity: Severity::CRITICAL);
        }
    }
}
