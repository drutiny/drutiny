<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Annotation\Param;

/**
 * Checks for existence of requested file/directory on specified path.
 */
class FilesExistenceAnalysis extends AbstractAnalysis {

  public function configure() {
    parent::configure();
    $this->addParameter(
      'directory',
      static::PARAMETER_OPTIONAL,
      'Absolute filepath to directory to scan',
      '%root'
    );
    $this->addParameter(
      'filenames',
      static::PARAMETER_OPTIONAL,
      'File names to include in the scan',
    );
    $this->addParameter(
      'type',
      static::PARAMETER_OPTIONAL,
      'File type as per file system. Allowed values are b for block special, c for character special, d for directory, f for regular file, l for symbolic link, p for FIFO and s for socket files.',
    );
    $this->addParameter(
      'exclude',
      static::PARAMETER_OPTIONAL,
      'Absolute file-paths to directories omit from scanning',
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
  public function gather(Sandbox $sandbox) {
    $directory = $this->getParameter('directory', '%root');
    $stat = $this->getTarget()->getService('drush')->status(['format' => 'json'])->run(function ($output) {
      return json_decode($output, true);
    });

    // Backwards compatibility. %paths is no longer present since Drush 8.
    if (!isset($stat['%paths'])) {
      foreach ($stat as $key => $value) {
        $stat['%paths']['%'.$key] = $value;
      }
    }

    $directory =  strtr($directory, $stat['%paths']);
    $command = ['find', $directory];

    // Add maxdepth to command if applicable.
    $maxdepth = $this->getParameter('maxdepth', NULL);
    if (is_int($maxdepth) && $maxdepth >= 0) {
      $command[] = '-maxdepth ' . $maxdepth;
    }

    $filetype = $this->getParameter('type', 'f');
    $command[] = "-type $filetype";

    $files = $this->getParameter('filenames', []);
    if (!empty($files)) {
      $conditions = [];
      foreach ($files as $file) {
        $conditions[] = '-iname "' . $file . '"';
      }
      $command[] = '\( ' . implode(' -or ', $conditions) . ' \)';
    }

    foreach ($this->getParameter('exclude', []) as $filepath) {
      $filepath = strtr($filepath, $stat['%paths']);
      $command[] = "! -path '$filepath'";
    }

    $command = implode(' ', $command);
    $this->logger->info('[' . __CLASS__ . '] ' . $command);
    $output = $this->target->getService('exec')->run($command);

    $this->set('has_results', !empty($output));

    $matches = array_filter(explode(PHP_EOL, $output));
    $results = [
      'found' => count($matches),
      'findings' => $matches,
    ];
    $this->set('results', $results);
  }
}
