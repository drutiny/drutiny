<?php

namespace Drutiny\Report\Format;

use Drutiny\Attribute\AsFormat;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Report\Format;
use Exception;
use ReflectionClass;

abstract class FilesystemFormat extends Format implements FilesystemFormatInterface
{
    /**
     * Location where the writeable directory is.
     */
    public readonly string $directory;

    /**
     * {@inheritdoc}
     */
    public function setWriteableDirectory(string $dir):void
    {
        $this->directory = $dir;
    }

    public function getWriteableDirectory(): string
    {
        return $this->directory;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtension(): string
    {
        $reflect = new ReflectionClass($this);
        $attributes = $reflect->getAttributes(AsFormat::class);

        if (empty($attributes)) {
            throw new Exception(get_class($this) . " has no AsFormat attribute.");
        }

        $format = $attributes[0]->newInstance();
        return $format->extension;
    }
}
