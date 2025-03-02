<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;

/**
 * Generic modules are enabled check.
 */
class ModulesEnabled extends Audit
{

    public function configure():void
    {
        $this->setDeprecated();
    }

  /**
   * @inheritdoc
   */
    public function audit(Sandbox $sandbox)
    {
        $modules = $sandbox->getParameter('modules');
        if (empty($modules)) {
            return true;
        }

        $notEnabled = [];
        foreach ($modules as $moduleName) {
            try {
                if (!$sandbox->drush()->moduleEnabled($moduleName)) {
                    throw new \Exception($moduleName);
                }
            } catch (\Exception $e) {
                $notEnabled[] = $moduleName;
            }
        }
        if (!empty($notEnabled)) {
            $sandbox->setParameter('notEnabled', $notEnabled);
            return false;
        }
      // Seems like the best way to comma separate things.
        else {
            $sandbox->setParameter('enabled', '`' . implode('`, `', $modules) . '`');
        }

        return true;
    }
}
