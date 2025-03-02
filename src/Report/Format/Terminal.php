<?php

namespace Drutiny\Report\Format;

use Fiasco\SymfonyConsoleStyleMarkdown\Renderer;
use Drutiny\Attribute\AsFormat;
use Drutiny\Report\RenderedReport;
use Drutiny\Report\Report;

#[AsFormat(
    name: 'terminal',
    extension: 'md'
)]
class Terminal extends Markdown
{
    /**
     * Change the report template to use a profile dependency report format.
     */
    public function setDependencyReport()
    {
        $this->definition = $this->definition->with(content: $this->loadTwigTemplate('report/dependency'));
    }

    /**
     * {@inheritdoc}
     */
    public function render(Report $report):RenderedReport
    {
        parent::render($report);
        $this->buffer->write(Renderer::createFromMarkdown($this->buffer->fetch()));
        return new RenderedReport('terminal', $this->buffer);
    }
}
