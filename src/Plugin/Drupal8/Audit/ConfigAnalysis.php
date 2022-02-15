<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractAnalysis;

/**
 * Check a configuration is set correctly.
 */
class ConfigAnalysis extends AbstractAnalysis
{


    public function configure()
    {
        parent::configure();
        $this->addParameter(
           'collection',
           static::PARAMETER_OPTIONAL,
           'The collection the config belongs to.'
        );
    }

  /**
   * @inheritDoc
   */
    public function gather(Sandbox $sandbox)
    {
        $collection = $this->getParameter('collection');

        $config = $this->target->getService('drush')->configGet($collection, [
        'format' => 'json',
        'include-overridden' => true,
        ])->run(function ($output) {
          return json_decode($output);
        });

        $this->set('config', (array) $config);
    }
}
