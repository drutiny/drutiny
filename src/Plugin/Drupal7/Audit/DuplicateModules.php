<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Symfony\Component\Yaml\Yaml;

/**
 * Duplicate modules.
 */
class DuplicateModules extends Audit {

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $config = $sandbox->drush(['format' => 'json'])->status();
    $docroot = $config['root'];

    $command = <<<CMD
find $docroot -name '*.module' -type f |\
grep -Ev 'drupal_system_listing_(in)?compatible_test' |\
grep -oe '[^/]*\.module' | grep -Ev '^\.module' | cut -d'.' -f1 | sort |\
uniq -c | sort -nr | awk '{print $2": "$1}'
CMD;

    $output = $sandbox->exec($command);
    $duplicateModules = array_filter(Yaml::parse($output), function ($count) {
      return $count > 1;
    });

    $modules = [];
    foreach ($duplicateModules as $module => $count) {
      $modules[] = [
        'module' => $module,
        'count' => $count,
      ];
    }

    $this->set('modules', $modules);
    return count($modules) === 0;
  }

}
