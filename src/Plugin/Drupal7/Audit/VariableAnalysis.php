<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;
use Symfony\Component\Process\Process;

/**
 * Check a configuration is set correctly.
 * @Param(
 *  name = "key",
 *  description = "The name of the variable to compare.",
 *  type = "string",
 * )
 * @Param(
 *  name = "value",
 *  description = "The value to compare against",
 *  type = "mixed",
 * )
 * @Param(
 *  name = "comp_type",
 *  description = "The comparison operator to use",
 *  type = "string",
 *  default = "=="
 * )
 * @Param(
 *  name = "required_modules",
 *  description = "An optional array of modules required in order to check variables",
 *  type = "array",
 *  default = {}
 * )
 * @Param(
 *  name = "default",
 *  description = "An optional default value if a value is not found",
 *  type = "mixed",
 *  default = "no-value-provided"
 * )
 * @Token(
 *  name = "reading",
 *  description = "The value read from the Drupal variables system",
 *  type = "mixed"
 * )
 */
class VariableAnalysis extends AbstractAnalysis
{
    /**
     * @inheritDoc
     */
    public function gather(Sandbox $sandbox)
    {
        $key = $sandbox->getParameter('key');

        $vars = $this->target->getService('drush')->variableGet($key, [
            'format' => 'json',
          ])->run(function (Process $process) {
            $output = $process->getOutput();
            if ($process->isSuccessful()) {
                return json_decode($output, true);
            }
            else {
                return [];
            }
            
          });

        $this->set($key, $vars[$key] ?? null);
    }

    /**
     * @inheritDoc
     */
    public function configure():void
    {
        parent::configure();
        $this->addParameter(
            'key',
            static::PARAMETER_OPTIONAL,
            'The name of the variable to get.'
        );
    }
}
