<?php

namespace Drutiny\Attribute;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
class UsePlugin
{
    public function __construct(
        public readonly string $name,
        public readonly string $as = '$plugin'
    ) {
    }
}
