<?php

namespace DrutinyTests;

use Drutiny\Kernel as DrutinyKernel;

class Kernel extends DrutinyKernel
{
    protected function getWorkingDirectory(): string
    {
        return dirname(__DIR__);
    }

    protected function writePhpContainer(): void
    {
       // Do not write during phpunit testing.
    }
}
