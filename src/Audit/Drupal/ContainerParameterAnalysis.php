<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Attribute\DataProvider;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Target\Service\Drush;

class ContainerParameterAnalysis extends AbstractAnalysis
{
    #[DataProvider]
    protected function getParameters(): void
    {
        $drush = $this->target->getService('drush');
        assert($drush instanceof Drush);
        $params = $drush->runtime(function () {
            $getParams = \Closure::bind(fn () => $this->parameters, \Drupal::getContainer(), 'Drupal\Component\DependencyInjection\Container');
            return $getParams();
        });
        $this->set('params', $params);
    }
}
