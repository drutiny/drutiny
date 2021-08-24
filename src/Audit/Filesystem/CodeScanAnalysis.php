<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Annotation\Param;

class CodeScanAnalysis extends AbstractAnalysis
{

    public function configure()
    {
      parent::configure();
      $this->addParameter(
          'directory',
          static::PARAMETER_OPTIONAL,
          'Absolute filepath to directory to scan',
          '%root'
      );
      $this->addParameter(
          'exclude',
          static::PARAMETER_OPTIONAL,
          'Absolute filepaths to directories omit from scanning',
      );
      $this->addParameter(
          'filetypes',
          static::PARAMETER_OPTIONAL,
          'file extensions to include in the scan',
      );
      $this->addParameter(
          'patterns',
          static::PARAMETER_OPTIONAL,
          'patterns to run over each matching file.',
      );
      $this->addParameter(
          'allowlist',
          static::PARAMETER_OPTIONAL,
          'Patterns which the \'patterns\' parameter may yield false positives from',
      );
      $this->addParameter(
          'maxdepth',
          static::PARAMETER_OPTIONAL,
          'An optional max depth for the scan.',
      );
    }


  /**
   * @inheritdoc
   */
    public function gather(Sandbox $sandbox)
    {
        $directory = $this->getParameter('directory', '%root');
        $stat = $this->getTarget()->getService('drush')->status(['format' => 'json'])->run(function ($output) {
          return json_decode($output, true);
        });
        //$stat = $sandbox->drush(['format' => 'json'])->status();

        // Backwards compatibility. %paths is no longer present since Drush 8.
        if (!isset($stat['%paths'])) {
            foreach ($stat as $key => $value) {
              $stat['%paths']['%'.$key] = $value;
            }
        }

        $directory =  strtr($directory, $stat['%paths']);

        $command = ['find', $directory, '-type f'];

        // Add maxdepth to command if applicable.
        $maxdepth = $this->getParameter('maxdepth', NULL);
        if (is_int($maxdepth) && $maxdepth >= 0) {
            $command[] = '-maxdepth ' . $maxdepth;
        }

        $types = $this->getParameter('filetypes', []);

        if (!empty($types)) {
            $conditions = [];
            foreach ($types as $type) {
                $conditions[] = '-iname "*.' . $type . '"';
            }
            $command[] = '\( ' . implode(' -or ', $conditions) . ' \)';
        }

        foreach ($this->getParameter('exclude', []) as $filepath) {
            $filepath = strtr($filepath, $stat['%paths']);
            $command[] = "! -path '$filepath'";
        }

        $command[] = '| (xargs grep -nEH';
        $command[] = '"' . implode('|', $this->getParameter('patterns', [])) . '" || exit 0)';

        $allowlist = $this->getParameter('allowlist', []);
        if (!empty($allowlist)) {
            $command[] = "| (grep -vE '" . implode('|', $allowlist) . "' || exit 0)";
        }

        $command = implode(' ', $command);
        $this->logger->info('[' . __CLASS__ . '] ' . $command);
        $output = $this->target->getService('exec')->run($command);

        $this->set('has_results', !empty($output));

        $matches = array_filter(explode(PHP_EOL, $output));
        $matches = array_map(function ($line) {
            list($filepath, $line_number, $code) = explode(':', $line, 3);
            return [
            'file' => $filepath,
            'line' => $line_number,
            'code' => trim($code),
            'basename' => basename($filepath)
            ];
        }, $matches);

        $results = [
          'found' => count($matches),
          'findings' => $matches,
          'filepaths' => array_values(array_unique(array_map(function ($match) use ($stat) {
              return str_replace($stat['%paths']['%root'], '', $match['file']);
          }, $matches)))
        ];

        $this->set('results', $results);
    }
}
