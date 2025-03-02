<?php

namespace Drutiny\Report;

use Exception;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Drutiny\Attribute\AsFormat;
use Drutiny\Profile\FormatDefinition;

abstract class Format implements FormatInterface
{
    protected string $namespace;
    protected FormatDefinition $definition;
    protected BufferedOutput $buffer;

    public function __construct(protected OutputInterface $output, protected LoggerInterface $logger)
    {
        $this->buffer = new BufferedOutput($output->getVerbosity(), true);
        $this->configure();
    }

    protected function configure()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setDefinition(FormatDefinition $definition):FormatInterface
    {
        $this->definition = $definition;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName():string
    {
        $reflect = new ReflectionClass($this);
        $attributes = $reflect->getAttributes(AsFormat::class);

        if (empty($attributes)) {
            throw new Exception(get_class($this) . " has no format attribute.");
        }

        $format = $attributes[0]->newInstance();
        return $format->name;
    }
}
