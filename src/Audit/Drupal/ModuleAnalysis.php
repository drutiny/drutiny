<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Attribute\DataProvider;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Helper\TextCleaner;
use Drutiny\Policy;
use Drutiny\Policy\Dependency;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Generic module is enabled check.
 *
 */
#[Dependency('Drupal.isBootstrapped')]
class ModuleAnalysis extends AbstractAnalysis
{
    public function prepare(Policy $policy): ?string
    {
        return static::class;
    }

    #[DataProvider]
    public function listModules():void
    {
        try {
            $list = $this->target->getService('drush')
            ->pmList([
              'format' => 'json',
              'type' => 'module',
              'fields' => 'project,package,path,status,version,display_name,type,name'
            ])
            ->run(function ($output) {
                return TextCleaner::decodeDirtyJson($output);
            });
        } catch (ProcessFailedException $e) {
          // E.g. The requested field, 'project', is not defined.
          // Retry without requesting specific fields.
            if (str_contains($e->getProcess()->getErrorOutput(), 'The requested field')) {
                $list = $this->target->getService('drush')
                ->pmList([
                'format' => 'json',
                'type' => 'module',
                ])
                ->run(function ($output) {
                    return TextCleaner::decodeDirtyJson($output);
                });
                foreach ($list as $name => &$data) {
                    // Pad with missing fields.
                    $data += [
                    'project' => '',
                    'package' => '',
                    'path' => '',
                    'status' => '',
                    'version' => '',
                    'display_name' => '',
                    'type' => '',
                    'name' => '',
                    ];
                }
            }
        }
        
        $this->set('modules', $list);
    }
}
