<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractAnalysis;

/**
 * Audit disk usage for a given directory.
 */
class DirectoryAnalysis extends AbstractAnalysis
{
    public function configure():void
    {
        parent::configure();
        $this->addParameter(
            'directory',
            static::PARAMETER_REQUIRED,
            'the directory to analyze',
        );
        $this->addParameter(
            'unit',
            static::PARAMETER_OPTIONAL,
            'the unit of measurement to describe the volume usage in. E.g. B,M,G,T.',
            'G'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function gather(Sandbox $sandbox)
    {
        $directory = $this->interpolate($this->getParameter('directory'));
        $unit = $this->getParameter('unit');

        $cmd = "du -sLB$unit $directory && df -B$unit $directory && df --inodes $directory";

        [$usage, $disk, $inode] = $this->target->run($cmd, function ($output) {
              return array_values(explode(PHP_EOL, $output));
        });

        // Remove all occurrences of the storage unit and '%' from the output.
        // This will allow the values to be used in conditional expressions.
        [$usage, $disk] = str_replace([$unit, '%'], '', [$usage, $disk]);

        // Parse the usage data into variables.
        [$du_size, $du_volume] = array_values(preg_split("/\t|\s/", $usage));
        [$disk_volume, $disk_capacity, $disk_used, $disk_free, $disk_usage, $disk_mountpoint] = array_values(array_filter(preg_split("/\t|\s/", $disk)));
        [$inode_volume, $inode_capacity, $inode_used, $inode_free, $inode_usage, $inode_mountpoint] = array_values(array_filter(preg_split("/\t|\s/", $inode)));

        $this->set(
            'filesystem',
            [
                'directory' => [
                    'volume' => $du_volume,
                    'size' => $du_size,
                    'unit' => $unit,
                ],
                'disk' => [
                    'volume' => $disk_volume,
                    'capacity' => (int)$disk_capacity,
                    'used' => (int)$disk_used,
                    'free' => (int)$disk_free,
                    'percent_used' => (int)$disk_usage,
                    'mountpoint' => $disk_mountpoint,
                    'unit' => $unit,
                ],
                'inode' => [
                    'volume' => $inode_volume,
                    'capacity' => (int)$inode_capacity,
                    'used' => (int)$inode_used,
                    'free' => (int)$inode_free,
                    'percent_used' => (int)$inode_usage,
                    'mountpoint' => $inode_mountpoint,
                ]
            ]
        );
    }
}
