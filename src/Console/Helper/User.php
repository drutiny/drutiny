<?php

namespace Drutiny\Console\Helper;

use Symfony\Component\EventDispatcher\EventDispatcher;

class User
{

    protected string $identity;

    public function __construct(EventDispatcher $eventDispatcher)
    {
        $user = posix_getpwuid(posix_geteuid());
        $this->identity = sprintf('%s@%s', $user['name'], gethostname());
        $eventDispatcher->dispatch($this, $this::class . '::getIdentity');
    }

    public function getIdentity():string
    {
        return $this->identity;
    }

    public function setIdentity(string $identity): void
    {
        $this->identity = $identity;
    }
}
