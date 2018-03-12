<?php

namespace Drutiny\Target;

use Drutiny\Driver\DrushInterface;
use Drutiny\Driver\ExecInterface;
use Drutiny\Driver\DrushTrait;
use Drutiny\Driver\Exec;

/**
 * @Drutiny\Annotation\Target(
 *  name = "drush"
 * )
 */
class DrushTarget extends Target implements DrushInterface, ExecInterface {
  use DrushTrait;

  protected $options = [];

  protected $alias;

  /**
   * @inheritdoc
   * Implements Target::parse().
   */
  public function parse($drush_alias) {
    $this->alias = $drush_alias;
    $data = $this->sandbox()->exec('drush site-alias @alias --format=json', [
      '@alias' => $drush_alias,
    ]);
    $options = json_decode($data, TRUE);

    $key = str_replace('@', '', $drush_alias);
    $this->options = isset($options[$key]) ? $options[$key] : array_shift($options);
    return $this;
  }

  /**
   * @inheritdoc
   * Implements Target::uri().
   */
  public function uri() {
    return isset($this->options['uri']) ? $this->options['uri'] : 'default';
  }

  /**
   * @inheritdoc
   * Overrides DrushTrait::runCommand().
   */
  public function runCommand($method, $args) {
    $process = new Exec($this->sandbox());
    return $process->exec('drush @alias @options @method @args', [
      '@method' => $method,
      '@args' => implode(' ', $args),
      '@options' => implode(' ', $this->drushOptions),
      '@alias' => $this->alias,
    ]);
  }

  /**
   * @inheritdoc
   * Implements ExecInterface::exec().
   */
  public function exec($command, $args = []) {
    $process = new Exec($this->sandbox());

    if (isset($this->options['remote-host'])) {

      $command = base64_encode(strtr($command, $args));
      $command = "echo $command | base64 --decode | sh";

      $defaults = $this->options + [
        'remote-user' => get_current_user(),
        'remote-host' => '',
        'ssh-options'  => '',
      ];
      unset($defaults['path-aliases']);
      $args = ['@command' => escapeshellarg($command)];

      $command = strtr('ssh ssh-options remote-user@remote-host @command', $defaults);

      $process = new Exec($this->sandbox());
    }
    return $process->exec($command, $args);
  }

}
