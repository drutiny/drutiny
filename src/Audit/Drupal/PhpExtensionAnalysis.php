<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Audit\AbstractAnalysis;

class PhpExtensionAnalysis extends AbstractAnalysis
{
  /**
   * @inheritdoc
   */
    public function gather()
    {
        $phpext = $this->target->getService('drush')->runtime(function () {
            return get_loaded_extensions();
        });

        $this->set('phpextensions', $phpext);
    }
}
