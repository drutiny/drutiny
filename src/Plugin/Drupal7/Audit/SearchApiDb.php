<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 * Search API Database.
 */
class SearchApiDb extends Audit
{

  /**
   * @inheritdoc
   */
    public function audit(Sandbox $sandbox)
    {
        if (!$sandbox->drush()->moduleEnabled('search_api_db')) {
            return Audit::NOT_APPLICABLE;
        }

      // Find out if there are active indexes using the db service class.
        $output = $sandbox->drush()->sqlQuery("SELECT COUNT(i.machine_name) as count FROM {search_api_index} i LEFT JOIN {search_api_server} s ON i.server = s.machine_name WHERE i.status > 0 AND s.class = 'search_api_db_service';");
        if (empty($output)) {
            $number_of_db_indexes = 0;
        } elseif (count($output) === 1) {
            $number_of_db_indexes = (int) $output[0];
        } else {
            $number_of_db_indexes = (int) $output[1];
        }
        $sandbox->setParameter('number_of_db_indexes', $number_of_db_indexes);
        $number_of_db_indexes > 1 ? $sandbox->setParameter('plural_index', 'es') : $sandbox->setParameter('plural_index', '');
      // No database indexes.
        if ($number_of_db_indexes === 0) {
            return Audit::SUCCESS;
        }
      // If the database is in use, find out how many nodes are in it.
        $output = $sandbox->drush()->sqlQuery('SELECT COUNT(item_id) FROM {search_api_db_default_node_index};');
      // There are some differences in running the command on site factory then
      // locally.
        if (count($output) == 1) {
            $nodes_in_search = (int) $output[0];
        } else {
            $nodes_in_search = (int) $output[1];
        }
        $sandbox->setParameter('nodes_in_search', $nodes_in_search);
        $max_size = (int) $sandbox->getParameter('max_size');
        if ($nodes_in_search < $max_size) {
            return Audit::WARNING;
        }
        return Audit::FAILURE;
    }
}
