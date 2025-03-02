<?php

namespace Drutiny\Audit\Unix;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[Parameter(name: 'command', description: 'The unix command to time', mode: Parameter::REQUIRED, type: Type::STRING)]
class TimeCommandAnalysis extends AbstractAnalysis
{
    #[DataProvider]
    protected function timeCommand()
    {
        $timing_command = '/usr/bin/time -f "%e %C" ';
        $unix_command = $this->getParameter('command');
        $output = $this->target->execute(Process::fromShellCommandline($timing_command . ' ' . $unix_command), function (Process $process) {
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            $output = $process->getErrorOutput();
            $lines = array_filter(explode(PHP_EOL, $output));
            // Only return the last line as that is all that is needed. Everything else would be error.
            return array_pop($lines);
        });

        list($timing, $command) = explode(' ', $output, 2);
        $this->set('timing', (float) $timing);
        $this->set('command', $command);
    }
}
