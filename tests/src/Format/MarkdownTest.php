<?php

namespace DrutinyTests\Fornat;

use Drutiny\Report\Format\Markdown;
use Drutiny\Report\Twig\Helper;
use DrutinyTests\KernelTestCase;
use League\CommonMark\Extension\Table\TableParser;
use Twig\Environment;
use Twig\Extra\Markdown\MarkdownRuntime;

class MarkdownTest extends KernelTestCase
{

    public function testTable()
    {
        foreach ([/*'Format/table_full',*/ 'Format/table_null_col2', 'Format/table_partial_null_col2'] as $fixture) {
            $tbl = $this->parseTable($text = $this->getFixture($fixture, 'md'));
            $this->assertCount(3, $tbl['headers'], "$fixture has 3 headers");
            $this->assertIsArray($tbl['rows'], "$fixture has rows");

            $this->assertCount(5, $tbl['rows'], "$fixture has 5 rows");

            foreach ($tbl['rows'] as $i => $row) {
                $this->assertCount(3, $row, "$fixture row $i has 3 cells.");
            }

            // Ensure the formatTables function doesn't mess parsing up at all.
            $text = Markdown::formatTables($text);
            $tbl = $this->parseTable($text);
            $this->assertCount(3, $tbl['headers'], "$fixture has 3 headers");
            $this->assertIsArray($tbl['rows'], "$fixture has rows");

            $this->assertCount(5, $tbl['rows'], "$fixture has 5 rows");

            foreach ($tbl['rows'] as $i => $row) {
                $this->assertCount(3, $row, "$fixture row $i has 3 cells.");
            }

            $twig = $this->container->get(Environment::class);
            $markdown = $twig->getRuntime(MarkdownRuntime::class);
            $html = $markdown->convert($text);

            $this->assertStringContainsString('<table', $html, "$fixture contains table element.");
            $this->assertStringContainsString('<thead', $html, "$fixture has table head element.");
            $this->assertStringContainsString('<tbody', $html, "$fixture has table body element.");
            $this->assertStringContainsString('<tr', $html, "$fixture has table row element.");
            $this->assertStringContainsString('<td', $html, "$fixture has table data element.");
            $this->assertStringContainsString('class="table table-hover"', $html, "$fixture has table classes");
            $this->assertStringContainsString('scope="col"', $html, "$fixture has scope attribute.");
            $this->assertStringContainsString('class="table-active"', $html, "$fixture has table-active class.");

            $this->assertEquals(3, substr_count($html, '<th scope="col"'), "$fixture rendered markdown contains 3 table headers.");
            $this->assertEquals(6, substr_count($html, '<tr'), "$fixture render contains 6 table rows.");
            $this->assertEquals(15, substr_count($html, '<td'), "$fixture render contains 15 table data cells.");
        }
    }

    protected function parseTable(string $table):array
    {
        $lines = array_map(fn ($line) => str_replace("\r", "", $line), explode("\n", $table));
        $lines = array_filter($lines);
        $table = [];

        $lines = array_map(fn ($line) => TableParser::split($line), $lines);

        $table['headers'] = array_shift($lines);
        array_shift($lines);
        $table['rows'] = $lines;
        return $table;
    }

    public function testMarkdownSyntax()
    {
        $text = $this->getFixture('Format/supported_syntax', 'md');

        $twig = $this->container->get(Environment::class);
        $markdown = $twig->getRuntime(MarkdownRuntime::class);
        $html = $markdown->convert($text);

        $this->assertStringContainsString('# header', $text);
        $this->assertStringContainsString('<h1', $html);

        $this->assertStringContainsString('## header', $text);
        $this->assertStringContainsString('<h2', $html);

        $this->assertStringContainsString('### header', $text);
        $this->assertStringContainsString('<h3', $html);

        $this->assertStringContainsString('#### header', $text);
        $this->assertStringContainsString('<h4', $html);

        $this->assertStringContainsString('##### header', $text);
        $this->assertStringContainsString('<h5', $html);

        $this->assertStringContainsString('*sandwich*', $text);
        $this->assertStringContainsString('<em>sandwich</em>', $html);

        $this->assertStringContainsString('**back**', $text);
        $this->assertStringContainsString('<strong>back</strong>', $html);

        $this->assertStringContainsString('`advanced`', $text);
        $this->assertStringContainsString('<code>advanced</code>', $html);

        $this->assertStringContainsString('```', $text);
        $this->assertStringContainsString('<pre><code>', $html);

        $this->assertStringContainsString('* List item 1', $text);
        $this->assertStringContainsString('- List item 1', $text);
        $this->assertEquals(2, substr_count($html, '<li>List item 1</li>'));

        $this->assertStringContainsString('2. Ordered List item 2', $text);
        $this->assertStringContainsString('1. Ordered List item 2', $text);
        $this->assertEquals(2, substr_count($html, '<li>Ordered List item 2</li>'));

        $this->assertStringContainsString('<a href="', $html);
    }

    public function testFilterChartTable()
    {
        $table = $this->parseTable($text = $this->getFixture('Format/table_null_col2', 'md'));
        $table['rows'] = array_map(fn($row) => array_map('trim', $row), $table['rows']);

        $md = Helper::filterChartTable($table['headers'], $table['rows'], []);

        $twig = $this->container->get(Environment::class);
        $markdown = $twig->getRuntime(MarkdownRuntime::class);
        $html = $markdown->convert($md);

        $this->assertStringContainsString('class="chart-unprocessed', $html);

        // Keyed rows support.
        $headers = ['Step', 'Runner 1', 'Runner 2'];
        $rows = [
            [
                'Step' => 1,
                'Runner 1' => 1,
                'Runner 2' => 1,
            ],
            [
                'Runner 1' => 2,
                'Runner 2' => 3,
                'Step' => 2,
            ],
            [
                'Runner 1' => 4,
                'Step' => 3,
            ],
            [
                'Step' => 4,
                'Runner 2' => 3,
            ],
        ];

        $md = Helper::filterChartTable($headers, $rows, []);

        // Remove the chart
        $md = substr($md, strpos($md, "\n"));
        $lines = array_values(array_filter(explode("\n", $md)));

        $this->assertStringStartsWith('Step |', $lines[0]);
        $this->assertStringStartsWith('1 |', $lines[2]);
        $this->assertStringStartsWith('2 |', $lines[3]);
        $this->assertStringStartsWith('3 |', $lines[4]);
        $this->assertStringStartsWith('4 |', $lines[5]);

        $table = $table = $this->parseTable($md);
        $table['rows'] = array_map(fn($row) => array_map('trim', $row), $table['rows']);

        $this->assertEquals('Runner 1', trim($table['headers'][1]));
        $this->assertEquals(1, $table['rows'][0][1]);
        $this->assertEquals(2, $table['rows'][1][1]);
        $this->assertEquals(4, $table['rows'][2][1]);
        $this->assertEmpty($table['rows'][3][1]);

        $this->assertEquals('Runner 2', trim($table['headers'][2]));
        $this->assertEquals(1, $table['rows'][0][2]);
        $this->assertEquals(3, $table['rows'][1][2]);
        $this->assertArrayNotHasKey(2, $table['rows'][2]);
        $this->assertEquals(3, $table['rows'][3][2]);
        
        $html = $markdown->convert($md);

        $this->assertEquals(2, substr_count($html, '<td></td>'));

        // Support where headers have keys

        $headers = [
            'step' => 'Step',
            'r1' => 'Runner 1',
            'r2' => 'Runner 2'
        ];
        $rows = [
            [
                'step' => 1,
                'r1' => 1,
                'r2' => 1,
            ],
            [
                'r1' => 2,
                'r2' => 3,
                'step' => 2,
            ],
            [
                'r1' => 4,
                'step' => 3,
            ],
            [
                'step' => 4,
                'r2' => 3,
            ],
        ];

        $md = Helper::filterChartTable($headers, $rows, []);

        // Remove the chart
        $md = substr($md, strpos($md, "\n"));
        $lines = array_values(array_filter(explode("\n", $md)));

        $this->assertStringStartsWith('Step |', $lines[0]);
        $this->assertStringStartsWith('1 |', $lines[2]);
        $this->assertStringStartsWith('2 |', $lines[3]);
        $this->assertStringStartsWith('3 |', $lines[4]);
        $this->assertStringStartsWith('4 |', $lines[5]);

        $table = $table = $this->parseTable($md);
        $table['rows'] = array_map(fn($row) => array_map('trim', $row), $table['rows']);

        $this->assertEquals('Runner 1', trim($table['headers'][1]));
        $this->assertEquals(1, $table['rows'][0][1]);
        $this->assertEquals(2, $table['rows'][1][1]);
        $this->assertEquals(4, $table['rows'][2][1]);
        $this->assertEmpty($table['rows'][3][1]);

        $this->assertEquals('Runner 2', trim($table['headers'][2]));
        $this->assertEquals(1, $table['rows'][0][2]);
        $this->assertEquals(3, $table['rows'][1][2]);
        $this->assertArrayNotHasKey(2, $table['rows'][2]);
        $this->assertEquals(3, $table['rows'][3][2]);
        
        $html = $markdown->convert($md);

        $this->assertEquals(2, substr_count($html, '<td></td>'));
    }
}
