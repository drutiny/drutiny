<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractAnalysis;

/**
 * Retrieve all Drupal settings. Settings can be set in settings.php in the $settings array.
 */
class SettingAnalysis extends AbstractAnalysis
{
  /**
   * @inheritDoc
   */
    public function gather(Sandbox $sandbox)
    {

        $drush = $this->target->getService('drush');
        $settings = $drush->runtime(function () {
            global $conf;
            return $conf;
        });

        if (!is_array($settings)) {
            throw new \Exception("Settings retrieved were not in a known format. Expected Array.");
        }

        $this->set('settings', $settings);
    }
}
