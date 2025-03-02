<?php

namespace Drutiny\Report\Format\LeagueMarkdown;

use Drutiny\Report\Format\Markdown;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use Twig\Extra\Markdown\MarkdownInterface;

class LeagueMarkdown implements MarkdownInterface
{
    public function __construct(private ConverterInterface $converter)
    {
    }

    public function convert(string $body): string
    {
        // Reformat to support old Parsedown syntax.
        $body = Markdown::formatTables($body);
        $body = Markdown::formatHeadings($body);

        return $this->converter->convert($body);
    }

    /**
     * As the col scope to th elements.
     */
    public static function tableCellScopeAttribute(TableCell $node): ?string
    {
        $tag = $node->getType() === TableCell::TYPE_HEADER ? 'th' : 'td';
        if ($tag == 'th') {
            return 'col';
        }
        return null;
    }

    public static function tableSectionClassAttribute(TableSection $node): ?string
    {
        $tag = $node->getType() === TableSection::TYPE_HEAD ? 'thead' : 'tbody';

        if ($tag == 'thead') {
            return 'table-active';
        }
        return null;
    }

    /**
     * Adds the table-active class to tr elements inside a thead.
     */
    public static function tableRowClassAttribute(TableRow $node): ?string
    {
        if (!($node->parent() instanceof TableSection)) {
            return null;
        }
        if ($node->parent()->getType() == TableSection::TYPE_HEAD) {
            return 'table-active';
        }
        return null;
    }
}
