<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractAnalysis;

/**
 * Perform List command and return formatted result.
 */
class FileListAnalysis extends AbstractAnalysis {

  public function configure() {
    parent::configure();
    $this->addParameter(
      'directory',
      static::PARAMETER_OPTIONAL,
      'Absolute filepath to directory to scan',
      '%root'
    );
    $this->addParameter(
      'filetypes',
      static::PARAMETER_OPTIONAL,
      'File extensions to include in the scan.',
    );
    $this->addParameter(
      'sortbytime',
      static::PARAMETER_OPTIONAL,
      'Sort listing by created timestamp.',
    );
    $this->addParameter(
      'limit',
      static::PARAMETER_OPTIONAL,
      'Limit listing result to specific count.',
    );
  }

/**
 * @inheritdoc
 */
  public function gather(Sandbox $sandbox) {
    $directory = $this->interpolate($this->getParameter('directory', '%root'));
    $stat = $this->target['drush']->export();

    // Backwards compatibility. %paths is no longer present since Drush 8.
    if (!isset($stat['%paths'])) {
      foreach ($stat as $key => $value) {
        $stat['%paths']['%'.$key] = $value;
      }
    }

    $directory =  strtr($directory, $stat['%paths']);
    $options[] = 'l';
    // Add sort by timestamp option if applicable.
    $sort = $this->getParameter('sortbytime', false);
    if ($sort) {
      $options[] = 't';
    }
    $command = [
      'ls',
      '-' . implode('', $options),
    ];

    $file_types = $this->getParameter('filetypes', []);
    if (!empty($file_types)) {
      if (!$this->endsWith($directory, '/')) {
        $directory .= '/';
      }
      foreach ($file_types as $file_type) {
        $command[] = $directory . '*.' . $file_type;
      }
    }
    else {
      $command[] = $directory;
    }

    // Add limit to command if applicable.
    $limit = $this->getParameter('limit', NULL);
    if (is_int($limit) && $limit >= 0) {
      $command[] = '| head -' . $limit;
    }

    $command = implode(' ', $command);
    $this->logger->info('[' . __CLASS__ . '] ' . $command);
    
    $files = $this->target->getService('exec')->run($command, function ($output) {
      return array_filter(explode(PHP_EOL, $output));
    });

    $results = [];
    $columns = [
      'permissions',
      'links',
      'user',
      'group',
      'size',
      'month',
      'day',
      'time',
      'file',
    ];
    foreach ($files as $file) {
      $row = [];
      for ($i = 0; $i < 9; $i++) {
        $row[$columns[$i]] = strtok($file, ' ');
        $file = $this->str_replace_once($row[$columns[$i]], '', $file);
      }
      $results[] = $row;
    }
    $this->set('has_results', !empty($files));
    $this->set('results', $results);
  }

  /**
   * Helper function to match end of string with required characters.
   */
  public function endsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    if( !$length ) {
      return true;
    }
    return substr( $haystack, -$length ) === $needle;
  }

  /**
   * Helper function to replace first occurrence from string.
   */
  public function str_replace_once($str_pattern, $str_replacement, $string){
    if (strpos($string, $str_pattern) !== false){
      return substr_replace($string, $str_replacement, strpos($string, $str_pattern), strlen($str_pattern));
    }
    return $string;
  }
}
