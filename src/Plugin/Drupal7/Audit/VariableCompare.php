<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Driver\DrushFormatException;
use Drutiny\Annotation\Param;
use Drutiny\Annotation\Token;

/**
 * Check a configuration is set correctly.
 * @Param(
 *  name = "key",
 *  description = "The name of the variable to compare.",
 *  type = "string",
 * )
 * @Param(
 *  name = "value",
 *  description = "The value to compare against",
 *  type = "mixed",
 * )
 * @Param(
 *  name = "comp_type",
 *  description = "The comparison operator to use",
 *  type = "string",
 *  default = "=="
 * )
 * @Param(
 *  name = "required_modules",
 *  description = "An optional array of modules required in order to check variables",
 *  type = "array",
 *  default = {}
 * )
 * @Param(
 *  name = "default",
 *  description = "An optional default value if a value is not found",
 *  type = "mixed",
 *  default = "no-value-provided"
 * )
 * @Token(
 *  name = "reading",
 *  description = "The value read from the Drupal variables system",
 *  type = "mixed"
 * )
 */
class VariableCompare extends Audit
{

  /**
   * @inheritDoc
   */
    public function audit(Sandbox $sandbox)
    {
        $key = $sandbox->getParameter('key');
        $value = $sandbox->getParameter('value');

        if ($required_modules = $sandbox->getParameter('required_modules')) {
            $required_modules = is_array($required_modules) ? $required_modules : [$required_modules];
            $info = $sandbox->drush(['format' => 'json'])->pmList();
            $missing_modules = array_diff($required_modules, array_keys($info));

            if (!empty($missing_modules)) {
                return Audit::NOT_APPLICABLE;
            }
        }

        try {
            $vars = $sandbox->drush([
            'format' => 'json'
            ])->variableGet();

            if (!isset($vars[$key])) {
                throw new DrushFormatException(__CLASS__ . ": $key is not a set variable.", '');
            }
            $reading = $vars[$key];
        } catch (DrushFormatException $e) {
            $sandbox->setParameter('exception', $e->getMessage());

            $default_value = $sandbox->getParameter('default', 'no-value-provided');

          // If no default value was provided then we can not provide an accruate
          // outcome based on the absense of a successful drush command.
            if ($default_value === 'no-value-provided') {
                return false;
            }

            $reading = $default_value;
        }

        $sandbox->setParameter('reading', $reading);

        $comp_type = $sandbox->getParameter('comp_type', '==');
        $sandbox->logger()->info('Comparative config values: ' . var_export([
        'reading' => $reading,
        'value' => $value,
        'expression' => 'reading ' . $comp_type . ' value',
        ], true));

        switch ($comp_type) {
            case 'lt':
            case '<':
                return $reading < $value;
            case 'gt':
            case '>':
                return $reading > $value;
            case 'lte':
            case '<=':
                return $reading <= $value;
            case 'gte':
            case '>=':
                return $reading >= $value;
            case 'ne':
            case '!=':
                return $reading != $value;
            case 'nie':
            case '!==':
                return $reading !== $value;
            case 'matches':
            case '~':
                return strpos($reading, $value) !== false;
            case 'identical':
            case '===':
                return $value === $reading;
            case 'in_array':
                return in_array($reading, $value);
            case 'regex':
                return preg_match("#$value#", $reading);
            case 'equal':
            case '==':
            default:
                return $value == $reading;
        }
    }
}
