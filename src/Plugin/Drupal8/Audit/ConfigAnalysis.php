<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Helper\TextCleaner;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Check a configuration is set correctly.
 */
#[Parameter(name: 'collection', description: 'The collection the config belongs to.', type: Type::STRING, mode: Parameter::REQUIRED)]
class ConfigAnalysis extends AbstractAnalysis
{
  /**
   * @inheritDoc
   */
    #[DataProvider]
    public function drushConfigGet():void
    {
        $collection = $this->getParameter('collection');

        $drush = $this->target->getService('drush');
        $command = $drush->configGet($collection, [
          'format' => 'json',
          'include-overridden' => true,
        ]);
        try {
            $config = $command->run(function ($output) {
                return TextCleaner::decodeDirtyJson($output);
            });
            $this->set('config', $config);
        } catch (ProcessFailedException $e) {
          // Check if the error was because the config did not exist.
            $config_missing_error = "Config $collection does not exist";
            $stderr = $e->getProcess()->getErrorOutput();
            if (strpos($stderr, $config_missing_error) !== false) {
                $this->set('config', null);
                return;
            }

          // Some other error we should continue to throw.
            throw $e;
        }
    }
}
