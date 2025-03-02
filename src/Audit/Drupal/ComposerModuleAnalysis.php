<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Attribute\DataProvider;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Audit\Exception\AuditNotApplicableException;
use Drutiny\Helper\TextCleaner;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Adds the contents of composer.lock to the dataBag.
 */
class ComposerModuleAnalysis extends AbstractAnalysis
{


    #[DataProvider]
    public function gather()
    {

        try {
            $composer_lock = $this->target->execute(Process::fromShellCommandline('cat $DRUSH_ROOT/../composer.lock || cat $DRUSH_ROOT/composer.lock || echo "[]"'), function ($output) {
                return json_decode($output, true);
            });
        } catch (ProcessFailedException $e) {
            throw new AuditNotApplicableException("Cannot find composer.lock", 0, $e);
        }

        try {
            $composer_json = $this->target->execute(Process::fromShellCommandline('cat $DRUSH_ROOT/../composer.json || cat $DRUSH_ROOT/composer.json || echo "[]"'), function ($output) {
                return json_decode($output, true);
            });
        } catch (ProcessFailedException $e) {
            throw new AuditNotApplicableException("Cannot find composer.json", 0, $e);
        }

        $composer_paths = [];
        foreach ($composer_json['extra']['installer-paths'] as $path => $types) {
            foreach ($types as $meta) {
                list($term, $type) = explode(':', $meta);
                if ($term != 'type') {
                    continue;
                }
                $composer_paths[$type] = str_replace('/{$name}', '', $path);
            }
        }

        $module_paths = [];
        foreach ($composer_lock['packages'] as $package) {
            if (strpos($package['name'], 'drupal/') !== 0) {
                continue;
            }
            if (!isset($composer_paths[$package['type']])) {
                continue;
            }
            list(, $name) = explode('/', $package['name'], 2);
            $module_paths[$name] = $composer_paths[$package['type']] . '/' . $name;
        }

        $module_list = $this->target->getService('drush')
          ->pmList([
            'format' => 'json',
            'type' => 'module',
            'fields' => 'project,package,path,status,version,display_name,type,name'
          ])
          ->run(function ($output) {
              return TextCleaner::decodeDirtyJson($output);
          });

      // Map composer data to modules.
        foreach ($module_list as $module_name => &$module_info) {
            $package_name = 'drupal/' . $module_name;

            $module_info['root_dependency'] = isset($composer_json['require'][$package_name]);
            // Find
            if (isset($module_paths[$module_name])) {
                $module_info['composer'] = 'drupal/' . $module_name;
                continue;
            }
            // submodule
            $paths = array_filter($module_paths, function ($path) use ($module_info) {
                return strpos('docroot/' . $module_info['path'], $path) !== false;
            });
            // Found a parent.
            if (count($paths)) {
                  $module_info['composer'] = 'drupal/' . key($paths);
                  $module_info['submodule'] = true;
                  $module_list[key($paths)]['modules'][] = $module_name;
            }
        }
        $this->set('modules', $module_list);
    }
}
