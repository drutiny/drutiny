<?php

namespace Drutiny;

use Drutiny\Attribute\RenamedFrom;
use Drutiny\Attribute\UseService;
use Drutiny\Audit\AuditInterface;
use Drutiny\Audit\AuditValidationException;
use Drutiny\Audit\Exception\AuditException;
use Drutiny\Target\TargetFactory;
use Drutiny\Target\TargetInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class AuditFactory
{
    /**
     * @var array<string, string>
     */
    public readonly array $registry;

    public function __construct(
        protected ContainerInterface $container,
        protected TargetFactory $targetFactory,
        protected Settings $settings,
    ) {
        $finder = new Finder;
        $files = $finder->in($settings->get('extension.dirs'))
            ->path('Audit')
            ->name('*.php');

        // Register all audit classes found in the extension directories
        $registry = [];
        foreach ($files as $file) {
            $class_name = $this->extractClassNameFromFile($file->getRealPath());
            if ($class_name) {
                $reflection = new ReflectionClass($class_name);
                if (!$reflection->implementsInterface(AuditInterface::class)) {
                    continue;
                }
                $registry[$class_name] = $class_name;
                // Check if the RenamedFrom attribute is present and if so add the old name to the registry.
                $attributes = $reflection->getAttributes(RenamedFrom::class);
                foreach ($attributes as $attribute) {
                    $registry[$attribute->newInstance()->oldName] = $class_name;
                }
            }
        }
        $this->registry = $registry;
    }

    /**
     * Check if an audit class is registered.
     *
     * @param string $audit_class
     *   The fully qualified class name of the audit class.
     */
    public function has(string $audit_class): bool
    {
        return isset($this->registry[$audit_class]);
    }

    /**
     * Extract the fully qualified class name from a PHP file.
     * Excludes abstract classes, interfaces, traits, and classes that don't follow PSR-4 naming.
     */
    protected function extractClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Get the expected class name from the filename (PSR-4 standard)
        $expectedClassName = pathinfo($filePath, PATHINFO_FILENAME);

        // Extract namespace
        $namespace = null;
        if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Check for abstract classes, interfaces, or traits - exclude them
        if (preg_match('/^(?:abstract\s+class|interface|trait)\s+/m', $content)) {
            return null;
        }

        // Extract only concrete class names
        $className = null;
        if (preg_match('/^class\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/m', $content, $matches)) {
            $className = trim($matches[1]);
        }

        // Ensure PSR-4 compliance: class name must match filename
        if (!$className || $className !== $expectedClassName) {
            return null;
        }

        // Return fully qualified class name
        if ($namespace && $className) {
            return $namespace . '\\' . $className;
        }

        return $className;
    }

    /**
     * Get an Audit object for the provided policy and target.
     */
    public function get(Policy $policy, TargetInterface $target):AuditInterface
    {
        $reflection = new ReflectionClass($policy->class);
        if (!$reflection->implementsInterface(AuditInterface::class)) {
            throw new AuditException("{$policy->class} does not implement " . AuditInterface::class);
        }
        return $this->mock($policy->class, $target);
    }

    /**
     * Get a mock audit instance without policy or target context.
     */
    public function mock(string $audit_class, ?TargetInterface $target = null):AuditInterface
    {
        $audit_class = $this->registry[$audit_class] ?? $audit_class;
        $reflection = new ReflectionClass($audit_class);
        if (!$reflection->implementsInterface(AuditInterface::class)) {
            throw new AuditException("$audit_class does not implement " . AuditInterface::class);
        }
        $target = $target ?? $this->targetFactory->create('none:none');
        $registry = [
            TargetInterface::class => $target,
            $target::class => $target
        ];

        $args = [];
        $construct = $reflection->getMethod('__construct');
        foreach ($construct->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!$parameter->hasType()) {
                throw new AuditValidationException("{$audit_class} constructor parameter '$name' has no type-hinting.");
            }
            $type = $parameter->getType();
            // Ues the provided TargetInterface object when required.
            // Use the container for all other types.
            $args[$name] = $registry[(string) $type] ?? $this->container->get((string) $type);
        }
        $audit = $reflection->newInstance(...$args);

        $attributes = [];
        do {
            $attributes = array_merge($attributes, $reflection->getAttributes(UseService::class));
            $reflection = $reflection->getParentClass();
        } while ($reflection);

        if (empty($attributes)) {
            return $audit;
        }

        do {
            $service = array_pop($attributes)->newInstance();
            $service->inject($audit, $this->container);
        } while (count($attributes));

        return $audit;
    }
}
