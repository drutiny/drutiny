<?php

namespace Drutiny;

use Drutiny\Attribute\ArrayType;
use Drutiny\Attribute\Description;
use Drutiny\Helper\MergeUtility;
use Drutiny\Profile\FormatDefinition;
use Drutiny\Profile\PolicyDefinition;
use Drutiny\Sandbox\ReportingPeriodTrait;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire:false)]
class Profile
{
    use ReportingPeriodTrait;

    #[Description('A list of policies that this profile runs.')]
    #[ArrayType('keyed', PolicyDefinition::class)]
    public readonly array $policies;

    #[Description('A list of policies that must pass for this profile to be applicable against a given target.')]
    #[ArrayType('keyed', PolicyDefinition::class)]
    public readonly array $dependencies;

    /**
     * @var \Drutiny\Profile\FormatDefinition[]
     */
    #[Description('An array for formats with repspective properties.')]
    #[ArrayType('keyed', FormatDefinition::class)]
    public readonly array $format;

    public function __construct(
        #[Description('The human readable name of the profile.')]
        public readonly string $title,
        #[Description('The machine-name of the profile.')]
        public readonly string $name,
        #[Description('Unique identifier such as a URL.')]
        public readonly string $uuid,
        #[Description('Where the profile is sourced from.')]
        public readonly string $source,
        #[Description('A description why the profile is valuable.')]
        public readonly string $description,
        #[Description('Language code')]
        public readonly string $language = 'en',
        array $policies = [],
        array $dependencies = [],
        public readonly array $excluded_policies = [],
        array $format = ['terminal' => []],
        #[Description('The URI this profile can be referenced and located by.')]
        public readonly ?string $uri = null,
    ) {
        $this->policies = $this->buildPolicyDefinitions($policies);
        $this->dependencies = $this->buildPolicyDefinitions($dependencies);
        $this->format = $this->buildFormatDefinitions($format);
    }

    /**
     * Produce profile object variation with altered properties.
     */
    public function with(...$properties):self
    {
        $args = MergeUtility::arrayMerge($this->export(), $properties);
        return new static(...$args);
    }


    /**
     * @deprecated Use dependencies property.
     */
    public function getDependencyDefinitions(): array
    {
        return $this->dependencies;
    }

    /**
     * Build format definitions.
     */
    private function buildFormatDefinitions(array $formats): array
    {
        $definitions = [];
        foreach ($formats as $name => $definition) {
            $definition['name'] = $name;
            $definitions[$name] = new FormatDefinition(...$definition);
        }
        return $definitions;
    }

    /**
     * Build policy definitions for constructor arrays.
     */
    private function buildPolicyDefinitions(array $policies): array
    {
        $definitions = [];
        foreach ($policies as $key => $definition) {
            if ($definition instanceof PolicyDefinition) {
                $definitions[$definition->name] = $definition;
                continue;
            }
            // The name is either the array key or a key on the $definition array.
            $name = is_string($key) ? $key : $definition['name'];
            if (in_array($name, $this->excluded_policies)) {
                continue;
            }
            $definition['name'] = $name;
            $definitions[$name] = new PolicyDefinition(...$definition);
        }
        uasort($definitions, fn($a, $b) => $a->sort($b));
        return $definitions;
    }

    /**
     * Merge an existing profile's policies and dependencies in with this one.
     */
    public function mergeWith(Profile $profile):Profile
    {
        $export = $profile->export();
        return $this->with(
            policies: $export['policies'],
            dependencies: $export['dependencies']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function export():array
    {
        $data = get_object_vars($this);
        $data['policies'] = array_map(fn($p) => $p->export(), $data['policies']);
        $data['dependencies'] = array_map(fn($d) => $d->export(), $data['dependencies']);
        $data['format'] = array_map(fn($f) => get_object_vars($f), $data['format']);

        // Fix Yaml::dump bug where it doesn't correctly split \r\n to multiple
        // lines.
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = str_replace("\r\n", "\n", $value);
            }
        }
        return $data;
    }
}
