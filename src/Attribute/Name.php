<?php

namespace Drutiny\Attribute;

use Attribute;

#[Attribute]
class Name implements KeyableAttributeInterface
{
    public function __construct(
        public readonly string $name
    ) {
    }

    public function getKey():string
    {
        return $this->name;
    }
}
