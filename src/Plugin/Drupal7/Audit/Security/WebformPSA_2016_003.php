<?php

namespace Drutiny\Plugin\Drupal7\Audit\Security;

use Drutiny\Audit\Drupal\ModuleEnabled;
use Drutiny\Sandbox\Sandbox;

/**
 *
 */
class WebformPSA_2016_003 extends ModuleEnabled
{

  /**
   *
   */
    public function audit(Sandbox $sandbox)
    {
      // Use the audit from ModuleEnable to validate check.
        $sandbox->setParameter('module', 'webform');
        if (!parent::audit($sandbox)) {
            return null;
        }

      // Look for NFL uploads.
      // See https://www.drupal.org/forum/newsletters/security-public-service-announcements/2016-10-10/drupal-file-upload-by-anonymous
        $output = $sandbox->drush()->sqlq("SELECT filename FROM {file_managed} WHERE UPPER(filename) LIKE '%NFL%' AND status = 0;");

        if (empty($output)) {
            $number_of_silly_uploads = 0;
            $sandbox->setParameter('files', '');
        } else {
            $output = explode(PHP_EOL, $output);
            $output = array_filter($output);
            $number_of_silly_uploads = count($output);

          // Format with markdown code backticks.
            $output = array_map(function ($filepath) {
                return "`$filepath`";
            }, $output);

            $sandbox->setParameter('files', '- ' . implode("\n- ", $output) . '</code>');
        }
        $sandbox->setParameter('number_of_silly_uploads', $number_of_silly_uploads);
        $sandbox->setParameter('plural', $number_of_silly_uploads > 1 ? 's' : '');
        $sandbox->setParameter('prefix', $number_of_silly_uploads > 1 ? 'are' : 'is');

        return $number_of_silly_uploads === 0;
    }
}
