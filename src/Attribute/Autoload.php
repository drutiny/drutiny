<?php

namespace Drutiny\Attribute;

use Attribute;

/**
 * Whether to autoload an object property.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Autoload
{
    public function __construct(
        /**
         * Whether to use autoloading on property.
         */
        public readonly bool $enabled = true,
        /**
         * Whether to autoload immediately at the constructor.
         */
        public readonly bool $early = false,
        /**
         * The name of the service to load.
         *
         * When null, the property type will be used.
         */
        public readonly ?string $service = null
    ) {
    }

    public function with(...$args)
    {
        $vars = get_object_vars($this);

        foreach ($vars as $name => $value) {
            $vars[$name] = $args[$name] ?? $value;
        }
        return new static(...$vars);
    }
}
