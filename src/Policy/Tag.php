<?php

namespace Drutiny\Policy;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire: false)]
class Tag
{
    public function __construct(
        public readonly string $name
    ) {
    }

    public function __toString()
    {
        return $this->name;
    }
}
