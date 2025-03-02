<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Driver\DrushFormatException;
use Drutiny\Annotation\Param;

/**
 * Generic module is disabled check.
 * @Param(
 *  name = "module",
 *  description = "The module to check is enabled.",
 *  type = "string"
 * )
 */
class ModuleDisabled extends Audit
{

  /**
   * @inheritdoc
   */
    public function audit(Sandbox $sandbox)
    {

        $module = $sandbox->getParameter('module');

        try {
            $info = $sandbox->drush(['format' => 'json'])->pmList();
        } catch (DrushFormatException $e) {
            return strpos($e->getOutput(), $module . ' was not found.') !== false;
        }

        if (!isset($info[$module])) {
            return true;
        }

        $status = strtolower($info[$module]['status']);

        return ($status != 'enabled');
    }
}
