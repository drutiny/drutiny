<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drutiny\Report;

use Drutiny\Report\Format\MarkdownHelper;
use Twig\Extra\Markdown\MarkdownInterface;

class DrutinyMarkdown implements MarkdownInterface
{
    private $converter;

    public function __construct(?MarkdownHelper $converter = null)
    {
        $this->converter = $converter ?: new MarkdownHelper();
    }

    public function convert(string $body): string
    {
        return $this->converter->text($body);
    }
}
