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

        $drush = $this->getTarget()->getService('drush');
        $command = $drush->configGet($collection, [
          'format' => 'json',
          'include-overridden' => true,
        ]);
        $config = $command->run(function ($output) {
          return json_decode($output, true);
        });

        $this->set('config', $config);
    }
}
