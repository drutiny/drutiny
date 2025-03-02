<?php

namespace Drutiny\Profile;

use Drutiny\Attribute\Description;
use Drutiny\Policy;
use Drutiny\Policy\Severity;
use Drutiny\PolicyFactory;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Autoconfigure(autowire:false)]
class PolicyDefinition
{
    #[Description('The parameter overrides to use for the policy in this profile.')]
    public readonly ParameterBagInterface $parameters;

    #[Description('Create parameters to pass to the audit before it is executed. Target object is available.')]
    public readonly ParameterBagInterface $build_parameters;

    #[Description('The severity override to use for the policy in this profile.')]
    public readonly Severity $severity;
    
    public function __construct(
        #[Description('A list of policies that must pass for this profile to be applicable against a given target.')]
        public readonly string $name,
        array $parameters = [],
        array $build_parameters = [],
        #[Description('Weighting to influence policy ordering in the profile.')]
        public readonly int $weight = 0,
        ?string $severity = null,
        // Internal policy reference can be used to load a policy instead of using the PolicyFactory.
        protected Policy|null $policy = null
    ) {
        $this->parameters = new FrozenParameterBag($parameters);
        $this->build_parameters = new FrozenParameterBag($build_parameters);

        if ($severity !== null) {
            $this->severity = is_numeric($severity) ? Severity::fromInt($severity) : Severity::from($severity);
        }
    }

    /**
     * A usort callback function to sort by weight.
     */
    public function sort(PolicyDefinition $definition)
    {
        if ($definition->weight == $this->weight) {
            $alphasort = [$definition->name, $this->name];
            sort($alphasort);
            return reset($alphasort) == $this->name ? 1 : -1;
        }
        return $definition->weight > $this->weight ? -1 : 1;
    }

    /**
     * Create and load a policy.
     */
    public static function fromPolicy(Policy $policy): static
    {
        return new static(
            name: $policy->name,
            parameters: $policy->parameters->all(),
            build_parameters: $policy->build_parameters->all(),
            weight: $policy->weight,
            severity: $policy->severity->value,
            policy: $policy
        );
    }

    /**
     * Get the policy for the profile.
     */
    public function getPolicy(PolicyFactory $factory):Policy
    {
        // If a base policy was provided when this defintion was constructed,
        // use that policy instead of loading it from the factory.
        $policy = $this->policy ?? $factory->loadPolicyByName($this->name);
        $args = ['weight' => $this->weight];
        
        if (isset($this->severity)) {
            $args['severity'] = $this->severity->value;
        }

        // Only override parameters if they are provided.
        if (count($this->parameters->all())) {
            $args['parameters'] = $this->parameters->all();
        }

        // Only override build_parameters if they are provided.
        if (count($this->build_parameters->all())) {
            $args['build_parameters'] = $this->build_parameters->all();
        }

        return $policy->with(...$args);
    }

    /**
     * Perpare data for export to Yaml format.
     */
    public function export():array
    {
        $properties = get_object_vars($this);

        // Convert ParameterBagInterfaces into arrays.
        $properties['parameters'] = $properties['parameters']->all();
        $properties['build_parameters'] = $properties['build_parameters']->all();

        // Convert Enums into values.
        if (isset($properties['severity'])) {
            $properties['severity'] = $properties['severity']->value;
        }
        
        // Only return non-empty values and keys.
        return array_filter($properties);
    }
}
