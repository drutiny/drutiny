<?php

namespace Drutiny\Event;

use Drutiny\Entity\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

class DataBagEvent extends Event
{

    protected $value;
    protected $path;
    protected $databag;

    public function __construct(DataBag $databag, $property_path, $value)
    {
        $this->path = $property_path;
        $this->value = $value;
        $this->databag = $databag;
    }

    public function getPropertyPath()
    {
        return $this->path;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getDatabag()
    {
        return $this->databag;
    }
}
