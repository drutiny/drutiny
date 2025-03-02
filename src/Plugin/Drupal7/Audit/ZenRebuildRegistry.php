<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 *
 */
class ZenRebuildRegistry extends Audit
{

  /**
   *
   */
    public function audit(Sandbox $sandbox)
    {

        $output = $sandbox->drush()->sqlQuery("SELECT * FROM {variable} WHERE name LIKE 'theme_%';");
        $themes_with_rebuild_enabled = [];
        foreach ($output as $row) {
            preg_match('/^theme_([a-zA-Z_]+)_settings/', $row, $matches);

          // 'theme_default' is also a variable we want to exclude.
            if (empty($matches)) {
                continue;
            }

            $theme_name = $matches[1];

            if (preg_match('/zen_rebuild_registry.;i:1/', $row)) {
                $themes_with_rebuild_enabled[] = $theme_name;
            }
        }

        if (count($themes_with_rebuild_enabled) > 0) {
            $sandbox->setParameter('number_of_themes', count($themes_with_rebuild_enabled));
            $sandbox->setParameter('themes', '<code>' . implode('</code>, <code>', $themes_with_rebuild_enabled) . '</code>');
            $sandbox->setParameter('plural', count($themes_with_rebuild_enabled) > 1 ? 's' : '');
            $sandbox->setParameter('prefix', count($themes_with_rebuild_enabled) > 1 ? 'are' : 'is');
            return false;
        }

        return true;
    }
}
