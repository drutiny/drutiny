<?php

namespace Drutiny\Audit\Unix;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Audit\DynamicParameterType;
use InvalidArgumentException;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Process\Process;

/**
 * Run a `find` command on a unix server.
 */
#[Parameter(
    name: 'search_directory',
    description: 'The location where the find command conduct the search.',
    type: Type::STRING,
    preprocess: DynamicParameterType::REPLACE,
    mode: Parameter::REQUIRED,
)]
#[Parameter(
    name: 'command',
    description: 'The arguments and options passed through to the find command',
    type: Type::STRING,
    preprocess: DynamicParameterType::REPLACE,
    mode: Parameter::REQUIRED,
)]
class FindAnalysis extends AbstractAnalysis
{
    #[DataProvider]
    protected function find():void
    {
        $banned_options = ['-delete', '-exec ', '-execdir'];
        $command = $this->getParameter('command');
        foreach ($banned_options as $option) {
            if (strpos($command, $option) !== false) {
                throw new InvalidArgumentException("Command contains forbidden option '$option': $command");
            }
        }

        $search_dir = $this->getParameter('search_directory');

        $args = ['test -d', $search_dir, '&&', 'find', $search_dir, $command];
        $command = Process::fromShellCommandline(implode(' ', $args));
        $results = $this->target->execute($command, function (Process $process, CacheItemInterface $cache) {
            if (!$process->isSuccessful()) {
                $cache->expiresAfter(0);
            }
            $output = $process->getOutput();
            if (empty($output)) {
                return [];
            }
            return array_filter(array_map('trim', explode(PHP_EOL, $output)));
        });

        $this->set('results', $results);
    }
}
