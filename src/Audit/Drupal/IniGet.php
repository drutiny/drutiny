<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Audit\AbstractComparison;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;

/**
 * Evaluate a PHP ini setting.
 * @Param(
 *  name = "setting",
 *  description = "The name of the ini setting to check.",
 *  type = "string"
 * )
 * @Param(
 *  name = "value",
 *  description = "The local value of the ini setting to compare for.",
 *  type = "mixed"
 * )
 * @Param(
 *  name = "comp_type",
 *  description = "The comparison operator to use for the comparison.",
 *  type = "string"
 * )
 */
class IniGet extends AbstractComparison
{

    public function configure():void
    {
        $this->addParameter(
            'setting',
            static::PARAMETER_OPTIONAL,
            'The name of the ini setting to check.'
        );
        $this->addParameter(
            'value',
            static::PARAMETER_OPTIONAL,
            'The local value of the ini setting to compare for.'
        );
        $this->addParameter(
            'comp_type',
            static::PARAMETER_OPTIONAL,
            'The comparison operator to use for the comparison.'
        );

        $this->setDeprecated();
    }

    public function audit(Sandbox $sandbox)
    {
        $ini = $this->target->getService('drush')->runtime(function () {
            return ini_get_all();
        });
        $setting = $this->getParameter('setting');

        if (!isset($ini[$setting])) {
            return false;
        }

        $this->set('local_value', $ini[$setting]['local_value']);

        return $this->compare($this->getParameter('value'), $ini[$setting]['local_value'], $sandbox);
    }
}
