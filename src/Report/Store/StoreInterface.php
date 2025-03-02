<?php

namespace Drutiny\Report\Store;

use Drutiny\Report\FormatInterface;
use Drutiny\Report\RenderedReport;
use Drutiny\Report\Report;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Console\Output\BufferedOutput;

interface StoreInterface
{
    
    /**
     * Store the formatted report.
     */
    public function store(RenderedReport $render, FormatInterface $format, Report $report): Uri;
}
