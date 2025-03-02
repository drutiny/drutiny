<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Driver\DrushFormatException;

/**
 * Check a configuration is set correctly.
 */
class CronLast extends Audit
{

  /**
   * @inheritDoc
   */
    public function audit(Sandbox $sandbox)
    {

        try {
            $vars = $sandbox->drush([
            'format' => 'json'
            ])->variableGet();

            if (!isset($vars['cron_last'])) {
                  return false;
            }

            $this->set('cron_last', date('l jS \of F Y h:i:s A', $vars['cron_last']));

            $time_diff = time() - $vars['cron_last'];
          // Fail if cron hasn't run in the last 24 hours.

            if ($time_diff > 86400) {
                return false;
            }
            return true;
        } catch (DrushFormatException $e) {
            return Audit::ERROR;
        }
    }
}
