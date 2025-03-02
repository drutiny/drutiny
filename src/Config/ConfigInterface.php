<?php

namespace Drutiny\Config;

interface ConfigInterface
{
    public function load(string $namespace):ConfigInterface;
    public function save():int|false;
}
