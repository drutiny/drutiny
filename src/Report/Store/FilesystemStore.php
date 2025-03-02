<?php

namespace Drutiny\Report\Store;

use Drutiny\Attribute\AsStore;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Report\FormatInterface;
use Drutiny\Report\RenderedReport;
use Drutiny\Report\Report;
use GuzzleHttp\Psr7\Uri;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsStore(name: 'fs')]
#[AutoconfigureTag('store')]
class FilesystemStore implements StoreInterface
{

    public function __construct(protected LoggerInterface $logger)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function store(RenderedReport $render, FormatInterface $format, Report $report): Uri
    {
        $ext = 'raw';
        $directory = '';

        if ($format instanceof FilesystemFormatInterface) {
            $ext = $format->getExtension();
            $directory = $format->getWriteableDirectory();
        }

        $filepath = $directory . '/' . $render->name . '.' . $ext;

        $stream = new StreamOutput(fopen($filepath, 'w'));
        $stream->write((string) $render);
      
        $this->logger->info("Written $filepath.");

        return new Uri('file://' . $filepath);
    }
}
