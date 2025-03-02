<?php

namespace Drutiny\PolicySource;

use Drutiny\Policy;
use Drutiny\SourceInterface;

interface PolicySourceInterface extends SourceInterface
{

  /**
   * Load a Drutiny\Policy object.
   */
    public function load(array $definition):Policy;
}
