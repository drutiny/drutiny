<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\AuditResponse\AuditResponse;

/**
 * Large files
 * @Token(
 *  name = "issues",
 *  description = "A list of files that reach the max file size.",
 *  type = "array"
 * )
 * @Token(
 *  name = "plural",
 *  description = "This variable will contain an 's' if there is more than one issue found.",
 *  type = "string",
 *  default = ""
 * )
 */
class LargeFiles extends Audit
{
    /**
     * @inheritdoc
     */
    public function configure():void
    {
        $this->addParameter(
            'max_size',
            static::PARAMETER_OPTIONAL,
            'Report files larger than this value measured in megabytes.'
        );
    }

    /**
     * @inheritdoc
     */
    public function audit(Sandbox $sandbox)
    {
        $max_size = (int) $this->getParameter('max_size', 20);

        $command = "find \$DRUSH_ROOT/\$DRUSH_FILES/ -type f -size +@sizeM -printf '@print-format' | sort -nr";
        $command = strtr($command, [
          '@size' => $max_size,
          '@print-format' => '%k\t%p\n',
        ]);

        $files = $this->target->run($command, function ($output) {
            $lines = array_filter(explode("\n", $output));
            return array_map(function ($line) {
                $parts = array_map('trim', explode("\t", $line));
                $size = number_format((float) $parts[0] / 1024, 2);
                $filename = trim($parts[1]);

                return [
                  'filename' => str_replace($this->target['drush.root'].'/', '', $filename),
                  'filepath' => $filename,
                  'size' => $size,
                  'unit' => 'MB',
                ];
                return "{$filename} [{$size} MB]";
            }, $lines);
        });

        // Setting legacy 2.x values.
        $this->set('issues', array_map(function ($file) {
            return strtr('filename [size unit]', $file);
        }, $files));
        $this->set('plural', count($files) > 1 ? 's' : '');

        // Setting more structured data available.
        $this->set('files', $files);

        return count($files) ? Audit::WARNING : true;
    }
}
