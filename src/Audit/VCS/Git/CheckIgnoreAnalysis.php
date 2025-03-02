<?php

namespace Drutiny\Audit\VCS\Git;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Drutiny\Target\FilesystemInterface;

class CheckIgnoreAnalysis extends AbstractAnalysis
{
    /**
     * {@inheritdoc}
     */
    public function configure():void
    {
        parent::configure();
        $dir = $this->target instanceof FilesystemInterface ? $this->target->getDirectory() : "";
        $this->addParameter(
            'paths',
            static::PARAMETER_REQUIRED,
            'A list of paths to check against a repositories git ignore setup.'
        );
        $this->addParameter(
            'repository',
            static::PARAMETER_OPTIONAL,
            'Location of the git repository.',
            $dir
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function gather(Sandbox $sandbox)
    {
        $paths = $this->get('paths');
        $paths = is_array($paths) ? implode(PHP_EOL, $paths) : $paths;
        $paths = base64_encode($paths);
        $repo = $this->get('repository');
        $cmd = sprintf("echo %s | base64 --decode | git -C %s check-ignore --verbose -n --no-index --stdin", $paths, $repo);
        $results = $this->target->run($cmd, function (Process $process) {
            $output = $process->getOutput();
            $results = [];
            foreach (array_filter(explode(PHP_EOL, $output)) as $line) {
                list($match, $search) = explode("\t", $line);
                list($file, $line, $rule) = explode(":", $match);
                $results[$search] = [
                'file' => $file,
                'line' => $line,
                'rule' => $rule,
                ];
            }
            return $results;
        });
        $this->set('results', $results);
    }
}
