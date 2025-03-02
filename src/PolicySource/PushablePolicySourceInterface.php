<?php

namespace Drutiny\PolicySource;

use Drutiny\Policy;

interface PushablePolicySourceInterface extends PolicySourceInterface
{

  /**
   * Push a policy up to the source to store.
   */
    public function push(Policy $policy, string $commit_msg = ''):Policy;
}
