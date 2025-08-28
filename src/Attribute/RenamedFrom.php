<?php

namespace Drutiny\Attribute;

use Attribute;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
#[Autoconfigure(autowire:false)]
class RenamedFrom {
  public function __construct(
    public string $oldName,
  ) {}
}