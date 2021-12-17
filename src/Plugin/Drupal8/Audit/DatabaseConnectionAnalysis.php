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
        return \Drupal\Core\Database\Database::getAllConnectionInfo();
    });
    $this->unset_user_pass($db_connections, ['username', 'password']);
    $this->set('database_connections', $db_connections);
  }

  /**
   * Helper function to unset non-required keys from connections array.
   * @param array $connections
   * @param array $unwanted_keys
   */
  public function unset_user_pass(array &$connections, array $unwanted_keys) {
    foreach ($unwanted_keys as $unwanted_key) {
      unset($connections[$unwanted_key]);
    }
    foreach ($connections as &$connection) {
      if (is_array($connection)) {
          $this->unset_user_pass($connection, $unwanted_keys);
      }
    }
  }

}
