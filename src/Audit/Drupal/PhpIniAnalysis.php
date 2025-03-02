<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Audit\AbstractAnalysis;

class PhpIniAnalysis extends AbstractAnalysis
{
  /**
   * @inheritdoc
   */
    public function gather()
    {
        $phpini = $this->target->getService('drush')->runtime(function () {
            return ini_get_all();
        });

        $settings = [];
        foreach ($phpini as $name => $values) {
            $settings[] = [
            'name' => $name,
            'global_value' => $values['global_value'],
            'local_value' => $values['local_value'],
            'access' => $values['access']
            ];
        }

        $this->set('phpini', $settings);
    }
}
