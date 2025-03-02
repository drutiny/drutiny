<?php

namespace Drutiny;

use Drutiny\Attribute\ArrayType;
use Drutiny\Attribute\Description;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Policy\Dependency;
use Drutiny\Entity\ExportableInterface;
use Drutiny\Helper\MergeUtility;
use Drutiny\Policy\AuditClass;
use Drutiny\Policy\Chart;
use Drutiny\Policy\PolicyType;
use Drutiny\Policy\Severity;
use Drutiny\Policy\Tag;
use Drutiny\Profile\PolicyDefinition;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire: false)]
class Policy implements ExportableInterface
{
    #[Description('What type of policy this is. Audit types return a pass/fail result while data types return only data.')]
    public readonly PolicyType $type;
    
    /**
     * @var \Drutiny\Policy\Tag[]
     */
    #[Description('A set of tags to categorize a policy.')]
    #[ArrayType('indexed', Tag::class)]
    public readonly array $tags;

    #[Description('What severity level the policy is rated at.')]
    public readonly Severity $severity;

    #[Description('Parameters are values that maybe used to configure an audit for use with the Policy.')]
    public readonly ParameterBagInterface $parameters;

    #[Description('Create parameters to pass to the audit before it is executed. Target object is available.')]
    public readonly ParameterBagInterface $build_parameters;

    /**
     * @var \Drutiny\Policy\Dependency[]
     */
    #[Description('A list of executable dependencies to require before auditing the policy against a target.')]
    #[ArrayType('indexed', Dependency::class)]
    public readonly array $depends;

    /**
     * @var \Drutiny\Policy\AuditClass[]
     */
    #[Description('A list of audit class version compatibilty constraints.')]
    #[ArrayType('indexed', AuditClass::class)]
    public readonly array $audit_build_info;

    /**
     * @var \Drutiny\Policy\Chart[]
     */
    #[Description('Configuration for any charts used in the policy messaging.')]
    #[ArrayType('indexed', Chart::class)]
    public readonly array $chart;

    public function __construct(
        #[Description('The human readable name of the policy.')]
        public readonly string $title,
        #[Description('The machine-name of the policy.')]
        public readonly string $name,
        #[Description('A description why the policy is valuable.')]
        public readonly string $description,
        #[Description('Unique identifier such as a URL.')]
        public readonly string $uuid,
        #[Description('Where the policy is sourced from.')]
        public readonly string $source,
        // Arrays and Enums are declared in the class and don't require Description attributes
        // in the constructor.
        string $type = 'audit',
        array $tags = [],
        string|Severity $severity = 'normal',
        array $parameters = [],
        array $build_parameters = [],
        array $depends = [],
        array $chart = [],
        #[Description('Weight of a policy to sort it amoung other policies.')]
        public readonly int $weight = 0,
        #[Description('A PHP Audit class to pass the policy to be assessed.')]
        public readonly string $class = AbstractAnalysis::class,
        #[Description('Language code')]
        public readonly string $language = 'en',
        #[Description('Content to communicate how to remediate a policy failure.')]
        public readonly string $remediation = '',
        #[Description('Content to communicate a policy failure.')]
        public readonly string $failure = '',
        #[Description('Content to communicate a policy success.')]
        public readonly string $success = '',
        #[Description('Content to communicate a policy warning (in a success).')]
        public readonly ?string $warning = '',
        #[Description('The URI this policy can be referenced and located by.')]
        public readonly ?string $uri = null,
        #[Description('Notes and commentary on policy configuration and prescribed usage.')]
        public readonly string $notes = '',
        array $audit_build_info = [],
    ) {
        $this->type = PolicyType::from($type);
        $this->severity = is_string($severity) ? Severity::from($severity) : $severity;
        $this->tags = array_map(fn(string $t) => new Tag($t), $tags);
        $this->parameters = new FrozenParameterBag($parameters);
        $this->build_parameters = new FrozenParameterBag($build_parameters);
        $this->depends = array_map(fn(string|array $d) => is_string($d) ? Dependency::fromString($d) : new Dependency(...$d), $depends);
        array_walk($chart, fn(&$c, $k) => $c = Chart::fromArray($c, $k));
        $this->chart = $chart;

        if (empty($audit_build_info)) {
            $audit_build_info = [AuditClass::fromClass($class)];
        }
        $this->audit_build_info = array_map(fn($c) => $this->buildAuditCompatibility($c), $audit_build_info);
    }

    /**
     * Produce policy object variation with altered properties.
     */
    public function with(...$properties):self
    {
        $args = MergeUtility::arrayMerge($this->export(), $properties);

        // Don't allow args to be kept if they're explicitly set.
        if (isset($properties['parameters'])) {
            $args['parameters'] = $properties['parameters'];
        }
        return new static(...$args);
    }

    /**
     * @throws \Drutiny\Policy\PolicyCompatibilityException
     */
    public function isCompatible(): bool
    {
        foreach ($this->audit_build_info as $compatibility) {
            $compatibility->isCompatible();
        }
        return true;
    }

    /**
     * The the audit compabitility information.
     */
    private function buildAuditCompatibility(string|array|AuditClass $built):AuditClass
    {
        return match (gettype($built)) {
            'string' => AuditClass::fromBuilt($built),
            'array' => new AuditClass(...$built),
            default => $built
        };
    }

    /**
     * Get a policy definition from the policy.
     */
    public function getDefinition():PolicyDefinition
    {
        return new PolicyDefinition(
            name: $this->name,
            parameters: $this->parameters->all(),
            build_parameters: $this->build_parameters->all(),
            weight: $this->weight,
            severity: $this->severity->value,
            // This allows the definition to be loaded without
            // the need for the PolicyFactory.
            policy: $this
        );
    }

    /**
     * {@inheritdoc}
     */
    public function export():array
    {
        $data = get_object_vars($this);
        $data['type'] = $data['type']->value;
        $data['severity'] = $data['severity']->value;
        $data['parameters'] = $data['parameters']->all();
        $data['build_parameters'] = $data['build_parameters']->all();
        $data['chart'] = array_map(fn($c) => get_object_vars($c), $data['chart']);
        $data['depends'] = array_map(fn($d) => $d->export(), $data['depends']);
        $data['tags'] = array_map(fn ($t) => $t->name, $this->tags);
        $data['audit_build_info'] = array_map(fn(AuditClass $a) => $a->asBuilt(), array_filter($data['audit_build_info'] ?? [], function (AuditClass $audit) {
            return $audit->version !== null;
        }));

        // This prevents older runtimes from not being able to instansiate the policy.
        if (empty($data['audit_build_info'])) {
            unset($data['audit_build_info']);
        }

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
