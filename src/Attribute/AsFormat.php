<?php

namespace Drutiny\Attribute;

use Attribute;

#[Attribute]
class AsFormat extends Name
{
    public function __construct(
        public readonly string $name,
        public readonly string|false $extension = false
    ) {
    }
}
