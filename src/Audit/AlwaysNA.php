<?php

namespace Drutiny\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 * An audit that always succeeds.
 *
 * This can be used by policies that utilise dependencies as their means of
 * auditing. As dependencies run first, this audit won't pass until all of
 * its dependencies pass first.
 */
class AlwaysNA extends Audit {

  public function audit(Sandbox $sandbox)
  {
    return Audit::NOT_APPLICABLE;
  }
}

 ?>
