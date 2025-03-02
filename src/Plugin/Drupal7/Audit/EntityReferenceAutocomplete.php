<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;
use Drutiny\Annotation\Token;

/**
 * Entity reference autocomplete
 * @Param(
 *  name = "threshold",
 *  description = "The limit of references allowed",
 *  type = "integer",
 *  default = 100
 * )
 * @Token(
 *  name = "errors",
 *  description = "An array of errors if any",
 *  type = "array",
 *  default = {}
 * )
 * @Param(
 *  name = "errors_count",
 *  description = "The number of errors found",
 *  type = "integer",
 *  default = 0
 * )
 */
class EntityReferenceAutocomplete extends Audit
{

    protected function requireEntityReference(Sandbox $sandbox)
    {
        return $sandbox->drush()->moduleEnabled('entityreference');
    }

  /**
   * Identify if entity reference fields are displaying select lists.
   *
   * Sites can see crippling performance if an entity reference field is being
   * used to display entities from a large node pool. This process will never
   * fail with max_execution_time and as a result can cause PHP-FPM to backup
   * and queue requests while it deals with an errant entity reference field.
   */
    public function audit(Sandbox $sandbox)
    {

        $errors = $sandbox->drush()->evaluate(function () {
            $results = db_query("SELECT fc.field_name, fc.data as field_info, fci.data as field_instance_info
        FROM {field_config} fc
        JOIN {field_config_instance} fci ON fc.id = fci.field_id
        WHERE fc.type = 'entityreference'");

            $errors = [];
            foreach ($results as $record) {
                if (!$record->field_info = unserialize($record->field_info)) {
                    continue;
                }

              // Correct configuration is to use an autocomplete as this will limit
              // the number of referenced entities from being rendered.
                if (strpos($record->field_instance_info['widget']['type'], 'autocomplete') > -1) {
                    continue;
                }

                $record->field_instance_info = unserialize($record->field_instance_info);
                if (!$record->field_instance_info = unserialize($record->field_instance_info)) {
                    continue;
                }

                $args = [];

                $handler_settings = isset($record->field_info['settings']['handler_settings']) ? $record->field_info['settings']['handler_settings'] : null;

              // Attempt to find the node types in the $field_info.
                if (isset($handler_settings['target_bundles'])) {
                    $args = array_keys($handler_settings['target_bundles']);
                } elseif (isset($handler_settings['view']['args'])) {
                    $args = [];
                    foreach ($handler_settings['view']['args'] as $arg) {
                      // If we're using views we can pass multiple entity types in
                      // contextually separated by a +.
                        $args = array_merge($args, explode('+', $arg));
                    }
                }

                switch ($record->field_info['settings']['target_type']) {
                    case 'node':
                        $count = db_query("SELECT count(*) as count FROM {node} node WHERE node.type in (" . $args . ")");
                        break;

                    case 'taxonomy_term':
                        $count = db_query("SELECT count(*) as count FROM {taxonomy_term_data}")->fetchField();
                        break;
                }

                if ($count > $sandbox->getParameter('threshold', 100)) {
                    $errors[] = "{$record->field_instance_info['label']} `{$record->field_instance['label']}`, found *{$count}* referenced entities.";
                }
            }
            return $errors;
        });

        $sandbox->setParameter('errors', $errors);
        $sandbox->setParameter('errors_count', count($errors));

        return count($errors) == 0;
    }
}
