<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AuditValidationException;
use Drutiny\Policy\Dependency;
use Symfony\Component\Process\Process;

/**
 * Checks for existence of requested file/directory on specified path.
 */
#[Dependency(expression: "Target.typeOf('Drutiny\\\Target\\\FilesystemInterface')")]
#[Parameter(name: 'contents_index', description: 'The index in the search results to retrieve file contents from. Default 0.', default: 0, type: Type::INTEGER)]
#[Parameter(name: 'filepath', description: "If a search isn't required, you can specify the explicit filepath to get contents from.", type: Type::STRING)]
class FilesContentsAnalysis extends FilesExistenceAnalysis
{

  /**
   * @inheritdoc
   */
    public function gather(Sandbox $sandbox)
    {
        if (!($filepath = $this->getParameter('filepath'))) {
            parent::gather($sandbox);
            $results = $this->get('results');
            $index = $this->get('contents_index');
            if ($results['found'] == 0 || !isset($results['findings'][$index])) {
                throw new AuditValidationException("File contents do not exist.");
            }
            $filepath = $results['findings'][$index];
        }

        $command = Process::fromShellCommandline('test ! -f ' . $filepath . ' ||  cat ' . $filepath);
        $this->set('contents', $this->target->execute($command, function ($output) {
            return trim($output);
        }));
    }
}
