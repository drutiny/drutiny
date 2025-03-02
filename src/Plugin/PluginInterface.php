<?php

namespace Drutiny\Plugin;

interface PluginInterface
{
    public function isHidden():bool;
    public function isInstalled():bool;
    public function getName():string;
}
