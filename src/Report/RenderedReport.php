<?php

namespace Drutiny\Report;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire:false)]
class RenderedReport
{

    const EXISTS_OVERWRITE = 1;
    const EXISTS_APPEND = 2;

    public function __construct(
        public readonly string $name,
        protected BufferedOutput $buffer,
        /**
         * The recommended write method suggested by the renderer.
         * Note that store classes may not obey the mode.
         */
        protected int $mode = self::EXISTS_OVERWRITE
    ) {
    }

    public function __toString()
    {
        return $this->buffer->fetch();
    }
}
