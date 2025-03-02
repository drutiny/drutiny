<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Helper\TextCleaner;
use Drutiny\Policy\Dependency;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks for existence of requested file/directory on specified path.
 */
#[Dependency(expression: "Target.typeOf('Drutiny\\\Target\\\FilesystemInterface')")]
#[Parameter(
    name: 'filepath',
    description: "specify the explicit filepath to get contents from.",
    type: Type::STRING,
    mode: Parameter::REQUIRED
)]
#[Parameter(
    name: 'format',
    description: "A format to parse the output through",
    type: Type::STRING,
    mode: Parameter::OPTIONAL,
    default: 'raw',
    enums: ['raw', 'json', 'yaml']
)]
class FileContentsAnalysis extends AbstractAnalysis
{
    #[DataProvider]
    public function getContents(): void
    {
        $filepath = $this->getParameter('filepath');

        $command = Process::fromShellCommandline('test ! -f ' . $filepath . ' ||  cat ' . $filepath);
        $contents = $this->target->execute($command, function ($output) {
            return trim($output);
        });

        $this->set('contents', match ($this->getParameter('format')) {
            'json' => TextCleaner::decodeDirtyJson($contents),
            'yaml' => Yaml::parse($contents),
            default => $contents
        });
    }
}
