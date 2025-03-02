<?php

namespace Drutiny\Entity;

trait SerializableExportableTrait
{

  /**
   * Implements Serializable::serialize().
   *
   * @return string Serialized string of an entity object.
   */
    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

  /**
   * Implements Serializable::unserialize().
   *
   * @return array Key/Value pair to import into the object.
   */
    public function unserialize($serialized): void
    {
        $this->__unserialize(unserialize($serialized));
    }

  /**
   * PHP 8.1 compatible serialization.
   *
   * @return array Key/Value pairs to serialize.
   */
    public function __serialize(): array
    {
        return $this->export();
    }

  /**
   * PHP 8.1 compatible unserialization.
   */
    public function __unserialize(array $serialized): void
    {
        $this->import($serialized);
    }

  /**
   * Export object data for serialization.
   */
    public function export(): array
    {
        return get_object_vars($this);
    }

  /**
   * Import data that was output from the export method.
   *
   * @param array $export The return value of the export method.
   */
    public function import(array $export): void
    {
        foreach ($export as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->{$key} = $value;
        }
    }
}
