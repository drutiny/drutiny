<?php

namespace Drutiny\Plugin;

use Drutiny\Plugin;

class PluginRequiredException extends \Exception
{

    public function __construct($plugin_name)
    {
        parent::__construct("Plugin '$plugin_name' required. " . PHP_EOL . "Please run plugin:setup $plugin_name");
    }
}
