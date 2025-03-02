<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;

/**
 * Check the version of Drupal project in a site.
 */
class ModuleVersion extends Audit
{

    public function configure():void
    {
        $this->setDeprecated();
    }

    public function audit(Sandbox $sandbox)
    {
        $module = $sandbox->getParameter('module');
        $version = $sandbox->getParameter('version');
        $comparator_method = $sandbox->getParameter('comparator');

        if (!method_exists("Composer\Semver\Comparator", $comparator_method)) {
            throw new \Exception("Comparator method not available: $comparator_method");
        }

        $info = $sandbox->drush(['format' => 'json'])->pmList();

        if (!isset($info[$module])) {
            return Audit::NOT_APPLICABLE;
        }

        $current_version = strtolower($info[$module]['version']);
        $sandbox->setParameter('current_version', $current_version);

        $sandbox->logger()->info("$comparator_method($current_version, $version)");

        return call_user_func("Composer\Semver\Comparator::$comparator_method", $current_version, $version);
    }
}
