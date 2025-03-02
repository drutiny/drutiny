<?php

namespace Drutiny\Attribute;

use Attribute;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
#[Autoconfigure(autowire: false)]
class Deprecated
{
    public function __construct(
        public readonly string $note,
    ) {
    }
}
