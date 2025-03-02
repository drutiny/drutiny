<?php

namespace Drutiny\Audit\Unix;

use DateTime;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Process\Process;

class ProcessStatusAnalysis extends AbstractAnalysis
{

    public function gather(Sandbox $sandbox)
    {
        // # Example of the `ps` output:
        // #     PPID   PID   USER         STAT %CPU COMMAND          STARTED WCHAN
        // #     1860   1909  cancerchat01 D     0.0 php               Apr 24 iterate_dir
        // #     15522  15576 cancerchat01 D     0.0 php               Apr 23 iterate_dir
        // #     20446  20496 cancerchat01 D     0.0 php             16:05:01 iterate_dir
        $this->set('procs', $this->target->execute(Process::fromShellCommandline('ps -eo lstart,pid,user:20,stat,pcpu=CPU,wchan:32,comm'), function (string $output, CacheItemInterface $cache) {
            $cache->expiresAfter(0);
            $lines = explode(PHP_EOL, $output);

            $procs = [];
            foreach ($lines as $line) {
                // First 25 characters are the lstart field.
                $lstart = trim(substr($line, 0, 25));
                $line = trim(substr($line, 25));
                $fields = array_filter(array_map('trim', explode(' ', $line)));
                array_unshift($fields, $lstart);

                if (!isset($headers)) {
                    $headers = $fields;
                    continue;
                }
                if (count($fields) > count($headers)) {
                    $fields[count($headers) - 1] = implode(' ', array_splice($fields, count($headers) -1));
                    $fields = array_splice($fields, 0, count($headers));
                }
                if (count($fields) < count($headers)) {
                    continue;
                }
                
                $fields = array_combine($headers, $fields);
                $fields['STARTED'] = new DateTime($fields['STARTED']);
                $procs[] = $fields;
            }
            return $procs;
        }));
    }
}
