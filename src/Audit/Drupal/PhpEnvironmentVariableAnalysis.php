<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Audit\AbstractAnalysis;

class PhpEnvironmentVariableAnalysis extends AbstractAnalysis
{
  /**
   * @inheritdoc
   */
    public function gather()
    {

        $env_vars = $this->target->getService('drush')->runtime(function () {
            return getenv();
        });

        $this->set('environment_variables', $env_vars);
    }
}
