<?php

namespace Drutiny\Target;

use InvalidArgumentException;
use Serializable;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire: false)]
class TargetExport
{

    public function __construct(public readonly string $targetReference, public readonly array $properties)
    {
    }

    public static function create(TargetInterface $target):static
    {
        $properties = [];
  
        foreach ($target->getPropertyList() as $key) {
            $value = $target->getProperty($key);
            $properties[$key] = $value;
        }
        return new static($target->getTargetName(), $properties);
    }

    public function __serialize():array
    {
        return [
            'targetReference' => $this->targetReference,
            'properties' => $this->properties,
        ];
    }

    public function __unserialize(array $data):void
    {
        $this->targetReference = $data['targetReference'];
        $this->properties = $data['properties'];
    }

    public function toTemporaryFile():string
    {
        $tmpfile = tempnam(sys_get_temp_dir(), 'drutinyTarget');
        file_put_contents($tmpfile, base64_encode(serialize($this)));
        return $tmpfile;
    }

    public static function fromTemporaryFile(string $filename):static
    {
        if (!file_exists($filename)) {
            throw new InvalidArgumentException("$filename does not exist.");
        }
        return unserialize(base64_decode(file_get_contents($filename)));
    }
}
