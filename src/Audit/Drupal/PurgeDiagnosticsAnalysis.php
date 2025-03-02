<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Attribute\DataProvider;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Helper\TextCleaner;
use Drutiny\Policy\Dependency;
use Drutiny\Target\Service\Drush;

#[Dependency('Drupal.moduleIsEnabled("purge_drush")')]
class PurgeDiagnosticsAnalysis extends AbstractAnalysis
{
    #[DataProvider]
    protected function runDiagnostics():void
    {
        $drush = $this->target->getService('drush');
        assert($drush instanceof Drush);

        $analysis = $drush->purgeDiagnostics(['format' => 'json'])->run(function ($output) {
            return TextCleaner::decodeDirtyJson($output);
        });

        $this->set('diagnostics', $analysis);
    }
}
