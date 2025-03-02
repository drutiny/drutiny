<?php

namespace Drutiny\Policy;

use Throwable;

class DependencyException extends \Exception
{
    protected $dependency;

    public function __construct(Dependency $dependency, string $message = '', Throwable|null $previous = null)
    {
        $this->dependency = $dependency;
        parent::__construct(sprintf("Policy dependency failed: %s (%s). %s", $dependency->description, $dependency->expression, $message), 0, $previous);
    }

    public function getDependency()
    {
        return $this->dependency;
    }
}
