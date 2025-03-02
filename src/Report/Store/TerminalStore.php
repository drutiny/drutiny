<?php

namespace Drutiny\Report\Store;

use Drutiny\Attribute\AsStore;
use Drutiny\Report\FormatInterface;
use Drutiny\Report\RenderedReport;
use Drutiny\Report\Report;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsStore(name: 'terminal')]
#[AutoconfigureTag('store')]
class TerminalStore implements StoreInterface
{

    public function __construct(protected OutputInterface $output)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function store(RenderedReport $renderedReport, FormatInterface $format, Report $report): Uri
    {
        $this->output->write((string) $renderedReport);
        return new Uri('stdout');
    }
}
