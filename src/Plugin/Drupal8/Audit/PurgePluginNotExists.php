<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Sandbox\Sandbox;
use Drutiny\Driver\DrushFormatException;

/**
 * Check a purge plugin exists.
 */
class PurgePluginNotExists extends PurgePluginExists
{

  /**
   * @inheritDoc
   */
    public function audit(Sandbox $sandbox)
    {
        return !parent::audit($sandbox);
    }
}
