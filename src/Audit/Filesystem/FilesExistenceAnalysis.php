<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractAnalysis;

/**
 * Checks for existence of requested file/directory on specified path.
 */
class FilesExistenceAnalysis extends AbstractAnalysis {

  public function configure() {
    parent::configure();
    $this->addParameter(
      'directories',
      static::PARAMETER_OPTIONAL,
      'List of absolute filepath to directory to scan',
      ['%root']
    );
    $this->addParameter(
      'filenames',
      static::PARAMETER_OPTIONAL,
      'File names to include in the scan',
    );
    $this->addParameter(
      'types',
      static::PARAMETER_OPTIONAL,
      'File type as per file system. Allowed values are b for block special, c for character special, d for directory, f for regular file, l for symbolic link, p for FIFO and s for socket files.',
    );
    $this->addParameter(
      'groups',
      static::PARAMETER_OPTIONAL,
      'File owned group.',
    );
    $this->addParameter(
      'users',
      static::PARAMETER_OPTIONAL,
      'File owned user.',
    );
    $this->addParameter(
      'smaller_than',
      static::PARAMETER_OPTIONAL,
      'File size smaller than x size. Use k for Kilobytes, M Megabytes and G for Gigabytes. E.g. 100k or 100M or 1G.',
    );
    $this->addParameter(
      'larger_than',
      static::PARAMETER_OPTIONAL,
      'File size larger than x size. Use k for Kilobytes, M Megabytes and G for Gigabytes. E.g. 100k or 100M or 1G.',
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
    $directories = $this->getParameter('directories', ['%root']);
    $stat = $this->target['drush']->export();

    // Backwards compatibility. %paths is no longer present since Drush 8.
    if (!isset($stat['%paths'])) {
      foreach ($stat as $key => $value) {
        $stat['%paths']['%'.$key] = $value;
      }
    }

    $processed_directories = [];
    foreach ($directories as $directory) {
      $processed_directories[] = strtr($directory, $stat['%paths']);
    }
    $command = ['find', implode(' ', $processed_directories)];

    // Add maxdepth to command if applicable.
    $maxdepth = $this->getParameter('maxdepth', NULL);
    if (is_int($maxdepth) && $maxdepth >= 0) {
      $command[] = '-maxdepth ' . $maxdepth;
    }

    // Add filetype to command. Default will be type file.
    $filetypes = $this->getParameter('types', ['f']);
    if (!empty($filetypes)) {
      $conditions = [];
      foreach ($filetypes as $filetype) {
        $conditions[] = '-type "' . $filetype . '"';
      }
      $command[] = '\( ' . implode(' -or ', $conditions) . ' \)';
    }

    // Add size parameter to command if applicable.
    $size_smaller_than = $this->getParameter('smaller_than', NULL);
    $size_larger_than = $this->getParameter('larger_than', NULL);
    if ($size_larger_than || $size_smaller_than) {
      $conditions = [];
      if (!is_null($size_larger_than)) {
        $conditions[] = '-size "+' . $size_larger_than . '"';
      }
      if (!is_null($size_smaller_than)) {
        $conditions[] = '-size "-' . $size_smaller_than . '"';
      }
      $command[] = '\( ' . implode(' -and ', $conditions) . ' \)';
    }

    // Add filenames to command if applicable.
    $files = $this->getParameter('filenames', []);
    if (!empty($files)) {
      $conditions = [];
      foreach ($files as $file) {
        $conditions[] = '-iname "' . $file . '"';
      }
      $command[] = '\( ' . implode(' -or ', $conditions) . ' \)';
    }

    // Add file group ownership option to command if applicable.
    $groups = $this->getParameter('groups', []);
    if (!empty($groups)) {
      $conditions = [];
      foreach ($groups as $group) {
        $conditions[] = '-group "' . $group . '"';
      }
      $command[] = '\( ' . implode(' -or ', $conditions) . ' \)';
    }

    // Add file user ownership option to command if applicable.
    $users = $this->getParameter('users', []);
    if (!empty($users)) {
      $conditions = [];
      foreach ($users as $user) {
        $conditions[] = '-user "' . $user . '"';
      }
      $command[] = '\( ' . implode(' -or ', $conditions) . ' \)';
    }

    foreach ($this->getParameter('exclude', []) as $filepath) {
      $filepath = strtr($filepath, $stat['%paths']);
      $command[] = "! -path '$filepath'";
    }

    $command = implode(' ', $command) . ' || exit 0';
    $this->logger->info('[' . __CLASS__ . '] ' . $command);
    
    $matches = $this->target->getService('exec')->run($command, function ($output) {
      return array_filter(explode(PHP_EOL, $output));
    });
    $this->set('has_results', !empty($matches));
    $results = [
      'found' => count($matches),
      'findings' => $matches,
    ];
    $this->set('results', $results);
  }
}
