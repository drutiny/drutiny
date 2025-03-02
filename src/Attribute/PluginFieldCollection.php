<?php

namespace Drutiny\Attribute;

use Exception;

class PluginFieldCollection
{
    protected array $collection;

    public function add(PluginField $field):self
    {
        $this->collection[$field->name] = $field;
        return $this;
    }

    public function get(string $name):PluginField
    {
        return $this->collection[$name] ?? throw new Exception("Field '$name' does not exist.");
    }

    public function getAll():array
    {
        return $this->collection;
    }
}
