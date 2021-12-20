<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;

/**
 * Provides all database connection information.
 */
class DatabaseConnectionAnalysis extends AbstractAnalysis {

  public function gather(Sandbox $sandbox) {
    $db_connections = $this->target->getService('drush')
      ->runtime(function() {
        $database_connections = \Drupal\Core\Database\Database::getAllConnectionInfo();
        $unset_username_pass = function (array &$connections, array $unwanted_keys) use (&$unset_username_pass) {
          foreach ($unwanted_keys as $unwanted_key) {
            unset($connections[$unwanted_key]);
          }
          foreach ($connections as &$connection) {
            if (is_array($connection)) {
              $unset_username_pass($connection, $unwanted_keys);
            }
          }
        };
        $unset_username_pass($database_connections, ['username', 'password']);
        return $database_connections;
      });
    $this->set('database_connections', $db_connections);
  }
}
