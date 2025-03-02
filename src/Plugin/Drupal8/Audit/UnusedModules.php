<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Driver\DrushFormatException;

/**
 *  Cron last run.
 */
class UnusedModules extends Audit
{

  /**
   *
   */
    public function audit(Sandbox $sandbox)
    {

        try {
            $list = $sandbox->drush(['format' => 'json', 'no-core'])->pmList();
        } catch (DrushFormatException $e) {
            return false;
        }

        $root = $this->target->getProperty('drush.root');

        $filepaths = $this->target
        ->run('find  $DRUSH_ROOT/modules -name \*.info.yml', function ($output) use ($root) {
            $lines = array_map('trim', explode(PHP_EOL, $output));
            array_walk($lines, function (&$line) use ($root) {
                $line = str_replace($root.'/', '', $line);
            });
            return $lines;
        });

        foreach ($list as $name => &$info) {
            $filename = $name . '.info.yml';
            $modules = array_filter($filepaths, function ($path) use ($filename) {
                return strpos($path, $filename) !== false;
            });
            $info['filepath'] = reset($modules);

            if (empty($info['filepath'])) {
                unset($list[$name]);
                continue;
            }
            $info['name'] = $name;
        }

        $enabled_paths = array_filter(array_map(function ($info) {
            return $info['status'] == 'Enabled' ? dirname($info['filepath']) : false;
        }, $list));

        $unused = array_filter($list, function ($info) use ($enabled_paths) {
            if ($info['status'] == 'Enabled') {
                return false;
            }
            $enabled = array_filter($enabled_paths, function ($path) use ($info) {
                return strpos($info['filepath'], $path) !== false;
            });
            return !count($enabled);
        });

        $this->set('unused_modules', array_map(function ($info) {
            return $info['display_name'];
        }, $unused));

        $this->set('unused_modules_info', $unused);
        $this->set('enabled_paths', $enabled_paths);

        return !count($unused);
    }
}
