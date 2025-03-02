<?php

namespace Drutiny\Report\Format\LeagueMarkdown;

use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class TableSectionRenderer implements NodeRendererInterface
{
    /**
     * @param TableSection $node
     *
     * {@inheritDoc}
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer):void
    {
        TableSection::assertInstanceOf($node);

        $attrs = $node->data->get('attributes');

        $tag = $node->getType() === TableSection::TYPE_HEAD ? 'thead' : 'tbody';

        if ($tag == 'thead') {
            $attrs['class'] = 'table-active';
        }
        $node->data->set('attributes', $attrs);
    }
}
