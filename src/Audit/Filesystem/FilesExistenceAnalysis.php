<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Policy\Dependency;
use Drutiny\Target\DrushTargetInterface;
use Drutiny\Target\FilesystemInterface;
use Symfony\Component\Process\Process;

/**
 * Checks for existence of requested file/directory on specified path.
 */

#[Parameter(
    name: 'filenames',
    description: 'File names to include in the scan',
    type: Type::ARRAY,
    default: []
)]
#[Parameter(
    name: 'types',
    description: 'File type as per file system. Allowed values are b for block special, c for character special, d for directory, f for regular file, l for symbolic link, p for FIFO and s for socket files.',
    default: ['f'],
    type: Type::ARRAY
)]
#[Parameter(
    name: 'groups',
    description: 'File owned group.',
    type: Type::ARRAY,
    default: []
)]
#[Parameter(
    name: 'users',
    description: 'File owned user.',
    default: [],
    type: Type::ARRAY,
)]
#[Parameter(
    name: 'smaller_than',
    description: 'File size smaller than x size. Use k for Kilobytes, M Megabytes and G for Gigabytes. E.g. 100k or 100M or 1G.',
    type: Type::STRING,
)]
#[Parameter(
    name: 'larger_than',
    description: 'File size larger than x size. Use k for Kilobytes, M Megabytes and G for Gigabytes. E.g. 100k or 100M or 1G.',
    type: Type::STRING,
)]
#[Parameter(
    name: 'exclude',
    description: 'Absolute file-paths to directories omit from scanning',
    default: [],
    type: Type::ARRAY,
)]
#[Parameter(
    name: 'maxdepth',
    description: 'An optional max depth for the scan.',
    type: Type::INTEGER,
)]
#[Dependency(expression: 'Target.typeOf("Drutiny\\\Target\\\FilesystemInterface")')]
class FilesExistenceAnalysis extends AbstractAnalysis
{

    public function configure():void
    {
        parent::configure();
        $dir = $this->target instanceof FilesystemInterface ? $this->target->getDirectory() : "";
        $this->addParameter(
            name: 'directories',
            description: 'List of absolute filepath to directory to scan',
            default: [$dir],
            type: Type::ARRAY
        );
    }

  /**
   * @inheritdoc
   */
    public function gather(Sandbox $sandbox)
    {
        $directories = $this->getParameter('directories');

        if ($this->target instanceof DrushTargetInterface) {
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
            $directories = $processed_directories;
        }

        $command = ['find', implode(' ', $directories)];

      // Add maxdepth to command if applicable.
        $maxdepth = $this->getParameter('maxdepth', null);
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
        $size_smaller_than = $this->getParameter('smaller_than', null);
        $size_larger_than = $this->getParameter('larger_than', null);
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
            $filepath = $this->interpolate($filepath);
            if ($this->target instanceof DrushTargetInterface) {
                $filepath = strtr($filepath, $stat['%paths']);
            }
            $command[] = "! -path '$filepath'";
        }

        $command = implode(' ', $command) . ' || exit 0';
        $this->logger->info('[' . __CLASS__ . '] ' . $command);

        $matches = $this->target->execute(Process::fromShellCommandline($command), function ($output) {
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
