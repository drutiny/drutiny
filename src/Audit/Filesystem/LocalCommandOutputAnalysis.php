<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Audit\AuditValidationException;
use Drutiny\Sandbox\Sandbox;

/**
 * Run a local command and analyse the output.
 */
class LocalCommandOutputAnalysis extends AbstractAnalysis
{

    public function configure():void
    {
        parent::configure();
        $this->addParameter(
            'local_commands',
            static::PARAMETER_REQUIRED,
            'Path to local command. Absolute or in user PATH.',
        );
        $this->addParameter(
            'commands',
            static::PARAMETER_REQUIRED,
            'A keyed array of commands. Each key will become a variable in the subsequent commands.',
        );
        $this->addParameter(
            'transfer',
            static::PARAMETER_OPTIONAL,
            'An array of files or directories to download locally from target before running command.'
        );
    }


  /**
   * @inheritdoc
   */
    public function gather(Sandbox $sandbox)
    {
        $commands = $this->getParameter('local_commands');
        if (is_string($commands)) {
            $commands = [$commands];
        }
        $commands = array_filter($commands);

        // Validate that local commands are available.
        foreach ($commands as $local_command) {
            if (!exec(sprintf('which %s', $local_command))) {
                throw new AuditValidationException("Could not fine local command: " . $local_command);
            }
        }

        // Create a new working dir
        do {
            $working_dir = sys_get_temp_dir() . '/LocalCommandOutputAnalysis_' . rand();
        } while (file_exists($working_dir));

        mkdir($working_dir);
        $this->set('working_dir', $working_dir);


        // Transfer the files from target to local working dir.
        foreach ($this->getParameter('transfer', []) as $filename => $remote_filepath) {
            $this->getLogger()->debug("Downloading $remote_filepath to $working_dir/$filename");
            $this->getTarget()->downloadFile(
            // Remote location
                $this->interpolate($remote_filepath),
                // Local location
                $working_dir . '/' . $this->interpolate($filename)
            );
        }

        // Execute the commands
        $command_outputs = [];
        foreach ($this->getParameter('commands') as $variable_name => $command) {
            $command_outputs[$variable_name] = $this->getTarget()
            ->getService('local')
            ->run($this->interpolate($command));

            if (!is_numeric($variable_name)) {
                $this->set($variable_name, $command_outputs[$variable_name]);
            }
        }

        $this->set('command_outputs', $command_outputs);

        // clean up.
        exec(sprintf('rm -rf %s', $working_dir));
    }
}
