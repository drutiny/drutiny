<?php

namespace Drutiny\Report\Format;

use Drutiny\Report\FormatInterface;
use Drutiny\Attribute\AsFormat;
use Drutiny\Report\RenderedReport;
use Drutiny\Report\Report;

#[AsFormat(
    name: 'markdown',
    extension: 'md'
)]
class Markdown extends HTML
{

    public function render(Report $report):RenderedReport
    {
        $render = parent::render($report);

        $markdown = self::formatTables((string) $render);

        $lines = explode(PHP_EOL, $markdown);
        array_walk($lines, function (&$line) {
            $line = trim($line);
        });

        $this->buffer->write(implode(PHP_EOL, $lines));
        return new RenderedReport($render->name, $this->buffer);
    }

    protected function prepareContent(array $variables):array
    {
        $sections = [];

      // In 3.x we support Twig TemplateWrappers to be passed directly
      // to the report format.
        foreach ($this->definition->content->getBlockNames() as $block) {
            $sections[] = $this->definition->content->renderBlock($block, $variables);
        }
        return $sections;
    }

    public static function formatHeadings(string $markdown): string
    {

        $lines = explode(PHP_EOL, $markdown);

        foreach ($lines as $idx => $line) {
            if (strpos($line, '#') === false) {
                continue;
            }

            // Convert headings like '##Heading' to '## Heading'.
            // This is to support the shift from Parsedown to Commonmark.
            $updated_line = strpos($line, '#') !== false ? preg_replace('/^((?:\s*)?[\#]+)([^ \#]+.*)$/', '$1 $2', $line) : $line;
            if ($updated_line != $line) {
                $lines[$idx] = $updated_line;
            }
        }
        return implode(PHP_EOL, $lines);
    }

    public static function formatTables(string $markdown): string
    {
        $lines = explode(PHP_EOL, $markdown);
        $table = [
            'start' => null,
            'widths' => [],
            'rows' => [],
            'cols' => 0,
            'leading_pipes' => false
        ];

        foreach ($lines as $idx => $line) {
            // Start table
            if ($table['start'] === null) {
                if (strpos($line, ' | ') !== false) {
                    $table['start'] = $idx;
                } else {
                    continue;
                }
            }
            // The line underneath the table headers
            elseif (($table['start'] + 1) == $idx) {
                $table['rows'][$idx] = [];
                continue;
            }
            // End table
            elseif (strpos($line, ' | ') === false) {
                // Only process if there were data cells to process. Just a header isn't a table.
                if (count($table['rows']) > 2) {
                    foreach ($table['rows'] as $line_idx => $row) {
                        $table_line = array_search($line_idx, array_keys($table['rows']));

                        // Look for the header seperator line and use dash '-' instead of space pads.
                        $pad = $table_line === 1 ? '-' : ' ';
                        $row = array_pad($row, $table['cols'], $pad);
                        foreach ($row as $i => $value) {
                            $row[$i] = str_pad($value, $table['widths'][$i], $pad, STR_PAD_RIGHT);
                        }

                        // For the header seperator line, we don't add pads to the first "column"
                        // on tables with leading pipes.
                        if ($pad == '-' && $table['leading_pipes']) {
                            $row[0] = '';
                        }

                        $lines[$line_idx] = implode(' | ', $row);
                    }
                }

                $table = [
                    'start' => null,
                    'widths' => [],
                    'rows' => [],
                    'cols' => 0,
                    'leading_pipes' => false
                ];
                continue;
            }

            // This is a table line.
            // Does the table line start with a |?
            if (strpos(trim($line), '|') === 0) {
                $table['leading_pipes'] = true;
            }

            $cells = array_map('trim', explode('|', $line));

            // Set the max width for each column.
            foreach ($cells as $i => $value) {
                $table['widths'][$i] = max($table['widths'][$i] ?? 0, strlen($value));
            }

            $table['cols'] = max(count($cells), $table['cols']);

            // Collect the cells.
            $table['rows'][$idx] = $cells;
        }

        return implode(PHP_EOL, $lines);
    }
}
