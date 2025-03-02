<?php

namespace Drutiny\Audit;

use Drutiny\Attribute\Parameter;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;

/**
 * A InputDefinition represents a set of valid command line Parameters and options.
 *
 * Usage:
 *
 *     $definition = new InputDefinition([
 *         new Parameter('name', Parameter::REQUIRED),
 *     ]);
 */
class InputDefinition
{
    /**
     * @var Parameter[]
     */
    private array $parameters = [];

    /**
     * Sets the Parameter objects.
     *
     * @param Parameter $parameters An array of Parameter objects
     */
    public function setParameters(Parameter ...$parameters)
    {
        $this->parameters = [];
        $this->addParameters(...$parameters);
    }

    /**
     * Adds an array of Parameter objects.
     *
     * @param Parameter $parameters An array of Parameter objects
     */
    public function addParameters(Parameter ...$parameters)
    {
        foreach ($parameters as $parameter) {
            $this->addParameter($parameter);
        }
    }

    /**
     * @throws LogicException When incorrect Parameter is given
     */
    public function addParameter(Parameter $parameter)
    {
        if (isset($this->parameters[$parameter->name])) {
            throw new LogicException(sprintf('An Parameter with name "%s" already exists.', $parameter->name));
        }

        $this->parameters[$parameter->name] = $parameter;
    }

    /**
     * Returns an Parameter by name or by position.
     *
     * @throws InvalidArgumentException When Parameter given doesn't exist
     */
    public function getParameter(string|int $name): Parameter
    {
        if (!$this->hasParameter($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" Parameter does not exist.', $name));
        }

        $parameters = \is_int($name) ? array_values($this->parameters) : $this->parameters;

        return $parameters[$name];
    }

    /**
     * Returns true if an Parameter object exists by name or position.
     */
    public function hasParameter(string|int $name): bool
    {
        $parameters = \is_int($name) ? array_values($this->parameters) : $this->parameters;

        return isset($parameters[$name]);
    }

    /**
     * Gets the array of Parameter objects.
     *
     * @return Parameter[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get parameter values from an array of values.
     *
     * @throws InvalidArgumentException When a value key is not in the parameter definition.
     * @throws InvalidArgumentException When a required parameter is missing.
     */
    public function fromValues(array $values):array
    {
        // Ensure unsupported parameters are not passed in.
        foreach (array_keys($values) as $name) {
            if (!$this->hasParameter($name)) {
                throw new InvalidArgumentException(sprintf('The "%s" parameter does not exist.', $name));
            }
        }
        foreach ($this->parameters as $parameter) {
            // Ensure required parameters are present.
            if ($parameter->isRequired() && !isset($values[$parameter->name])) {
                throw new InvalidArgumentException(sprintf('Missing required parameter "%s".', $parameter->name));
            }
            // Ensure parameter values meet validation requirements.
            if (isset($values[$parameter->name])) {
                $parameter->validate($values[$parameter->name]);
            }
        }
        return $values;
    }
}
