<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractComparison;

/**
 * Check a configuration is set correctly.
 *
 * @Token(
 *   name = "reading",
 *   description = "The value retrieve from the key in the Drupal site.",
 *   type = "mixed"
 * )
 */
class SettingCompare extends AbstractComparison
{
    public function configure():void
    {
        $this->addParameter(
            'key',
            static::PARAMETER_OPTIONAL,
            'The settings key to evauate',
            ''
        );
        $this->addParameter(
            'value',
            static::PARAMETER_OPTIONAL,
            'The value of the key you want to compare against.',
            ''
        );
        $this->addParameter(
            'conditional_expression',
            static::PARAMETER_OPTIONAL,
            'The expression language to evaludate. See https://symfony.com/doc/current/components/expression_language/syntax.html',
            'true'
        );
    }


    /**
     * @inheritDoc
     */
    public function audit(Sandbox $sandbox)
    {
        $key = $this->getParameter('key');
        $value = $this->getParameter('value');

        $drush = $this->getTarget()->getService('drush');
        $settings = $drush->runtime(function () {
            return \Drupal\Core\Site\Settings::getAll();
        });

        if (!is_array($settings)) {
            throw new \Exception("Settings retrieved were not in a known format. Expected Array.");
        }

        $keys = explode('.', $key);

        while ($k = array_shift($keys)) {
            if (!isset($settings[$k])) {
                $sandbox->logger()->info("Could not find '$k' value in $key. No such setting exists.");
                return false;
            }
            $settings = $settings[$k];
        }

        $reading = $settings;

        $this->set('reading', $reading);

        return $this->compare($reading, $value, $sandbox);
    }
}
