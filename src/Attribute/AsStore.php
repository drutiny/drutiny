<?php

namespace Drutiny\Attribute;

use Attribute;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[Attribute]
class AsStore extends Name
{
    public function __construct(
        public readonly string $name
    ) {
    }
}
