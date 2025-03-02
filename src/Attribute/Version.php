<?php

namespace Drutiny\Attribute;

use Attribute;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use InvalidArgumentException;

/**
 * Declare a class constant or property
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Version
{
    public readonly string $version;
    public readonly string $compatibilty;
    public readonly bool $compatible;

    /**
     * @throws \UnexpectedValueException
     */
    public function __construct(string $version = 'dev-main', ?string $compatibilty = null)
    {
        $versionParser = new VersionParser;

        /* @throws \UnexpectedValueException */
        $versionParser->normalize($version);

        $this->version = $version;

        if (empty($compatibilty)) {
            $compatibilty = '^' . $version;
        }

        $this->compatibilty = $versionParser->parseConstraints($compatibilty)->getPrettyString();

        $this->compatible = $this->compatibleWith($this->version);
//            throw new InvalidArgumentException("{$this->version} is not compatible with constraint: {$this->compatibilty}.");
    }

    /**
     * Determine if a given version string meets the compatibility constriants.
     */
    public function compatibleWith(string $version): bool
    {
        return Semver::satisfies($version, $this->compatibilty);
    }
}
