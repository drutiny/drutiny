<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Attribute\DataProvider;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Helper\TextCleaner;
use Drutiny\Policy;
use Drutiny\Policy\Dependency;

/**
 * Generic module is enabled check.
 *
 */
#[Dependency('Drupal.isBootstrapped')]
class RequirementsAnalysis extends AbstractAnalysis
{
    public function prepare(Policy $policy): ?string
    {
        return static::class;
    }

    #[DataProvider]
    public function listModules():void
    {
        $list = $this->target->getService('drush')
          ->coreRequirements([
            'format' => 'json',
            'fields' => 'title,severity,sid,description,value',
          ])
          ->run(function ($output) {
              return TextCleaner::decodeDirtyJson($output);
          });
        $this->set('requirements', $list);
    }
}
