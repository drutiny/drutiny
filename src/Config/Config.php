<?php

namespace Drutiny\Config;

use ArrayAccess;

class Config implements ArrayAccess, ConfigInterface
{
    public function __construct(
        protected ConfigFile|Config $parent,
        protected array &$data,
        protected string $namespace
    ) {
    }

    public function load(string $namespace):Config
    {
        if (!isset($this->data[$namespace])) {
            $this->data[$namespace] = [];
        }

        return new static($this, $this->data[$namespace], $namespace);
    }

    public function save(?string $namespace = null):int|false
    {
        if (isset($namespace) && isset($this->data[$namespace]) && empty($this->data[$namespace])) {
            unset($this->data[$namespace]);
        }
        return $this->parent->save($this->namespace);
    }

    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    public function __isset($name): bool
    {
        return isset($this->data[$name]);
    }

    public function __set($name, $value):void
    {
        $this->data[$name] = $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    public function keys()
    {
        return array_keys($this->data);
    }

    public function delete()
    {
        $this->data = [];
        return $this->parent->save($this->namespace);
    }
}
