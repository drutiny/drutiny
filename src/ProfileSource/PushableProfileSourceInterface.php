<?php

namespace Drutiny\ProfileSource;

use Drutiny\Profile;

interface PushableProfileSourceInterface extends ProfileSourceInterface
{

  /**
   * Push a profile up to the source to store.
   */
    public function push(Profile $profile, string $commit_msg = ''):Profile;
}
