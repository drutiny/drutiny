<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;

/**
 * Views Analysis
 */
class FieldTypeAnalysis extends AbstractAnalysis
{

  /**
   * {@inheritdoc}
   */
    public function gather(Sandbox $sandbox)
    {
        $data = $this->target->getService('drush')->runtime(function () {
            return field_info_field_types();
        });

        $this->set('field_types', $data);
    }
}
