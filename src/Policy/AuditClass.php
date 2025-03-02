<?php

namespace Drutiny\Policy;

use Composer\Semver\Comparator;
use Drutiny\Attribute\Version;
use Drutiny\Policy\Compatibility\IncompatibleVersionException;
use Drutiny\Policy\Compatibility\NoRuntimeVersionException;
use Drutiny\Policy\Compatibility\RuntimeOutdatedException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

class AuditClass
{
    public readonly Version|null $version;

    public function __construct(
        public readonly string $name,
        string|Version|null $version = null
    ) {
        if (is_string($version)) {
            $version = new Version($version);
        }
        $this->version = $version;
    }

    /**
     * Create a new AuditClass instance from an given class name.
     */
    public static function fromClass(string $class_name): static
    {
        try {
            $reflection = new ReflectionClass($class_name);
            $attributes = $reflection->getAttributes(Version::class);
    
            foreach ($attributes as $attribute) {
                return new static($class_name, $attribute->newInstance());
            }
        } catch (ReflectionException $e) {
            // Class doesn't exist so can't pull local version information.
        }
       
        return new static($class_name);
    }

    public static function fromBuilt(string $requirement): static
    {
        list($name, $version) = explode(':', $requirement, 2);
        $fromClass = self::fromClass($name);

        // When building from a requirement string, the actually version doesn't matter.
        return new static($name, new Version($version, $fromClass->version->compatibilty));
    }

    public function export(): array|string
    {
        if ($this->version == null) {
            return $this->name;
        }
        $vars = get_object_vars($this);
        $vars['version'] = $this->version->version;
        return $vars;
    }

    public function asBuilt(): string
    {
        return sprintf('%s:%s', $this->name, $this->version?->version ?? '');
    }

    /**
     * Check if the instance is compatible with the current runtime class.
     */
    public function isCompatible(): bool
    {
        // If version is not available then this is not a real compatibility check.
        // For backwards compatibility we'll return "true".
        if ($this->version === null) {
            return true;
        }

        if (!$this->version->compatible) {
            throw new IncompatibleVersionException($this, "{$this->name}:{$this->version->version} is not compatible with constraint: {$this->version->compatibilty}");
        }

        $runtime = static::fromClass($this->name);

        // If we require a version but a version is not present in the runtime
        // then we are not compatible.
        if ($runtime->version === null) {
            throw new NoRuntimeVersionException($this, "Runtime {$runtime->name} requires version {$this->version->compatibilty} but none is specified. An upgrade is required.");
        }

        // If the policy requires a later version then we need to upgrade.
        if (Comparator::greaterThan($this->version->version, $runtime->version->version)) {
            throw new RuntimeOutdatedException($this, "Runtime {$runtime->name} requires version {$this->version->compatibilty} but {$runtime->version->version} is specified. An upgrade is required.");
        }

        // Test if the runtime audit class is compatible with the policy version.
        if ($runtime->version->compatibleWith($this->version->version)) {
            return true;
        }
        
        // Policy is too old and cannot run on this runtime.
        throw new IncompatibleVersionException($this, "Runtime {$runtime->name} requires version {$runtime->version->compatibilty} but {$this->version->version} is specified in your policy. This policy is too old for your runtime.");
    }
}
