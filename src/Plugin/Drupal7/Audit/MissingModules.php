<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Annotation\Param;
use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Symfony\Component\Yaml\Yaml;

/**
 * Missing modules.
 * @Param(
 *  name = "key",
 *  description = "The name of the variable to compare.",
 *  type = "string",
 * )
 */
class MissingModules extends Audit {

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $rows = $this->target->getService('drush')->runtime(function () {
      $rows = [];

      // Grab all the modules in the system table.
      $query = db_query("SELECT filename, type, name FROM {system}");
      // Go through the query and check to see if the module exists in the directory.
      foreach ($query->fetchAll() as $record) {
        if ($record->name == 'default') {
          continue;
        }

        // Grab the checker.
        $check = drupal_get_filename($record->type, $record->name, $record->filename, FALSE);
        // If drupal_get_filename returns null = we got problems.
        if (!is_null($check)) {
          continue;
        }

        // Go ahead and set the row if all is well.
        $rows[$record->name] = array(
          'name' => $record->name,
          'type' => $record->type,
          'filename' => $record->filename,
        );
      }
      return $rows;
    });

    $this->set('messages', array_values(array_map(function ($row) {
      return "Cannot find {$row['type']} `{$row['name']}`. Expected to be in {$row['filename']}.";
    }, $rows)));

    return empty($rows);
  }

}
