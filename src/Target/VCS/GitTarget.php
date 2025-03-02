<?php

namespace Drutiny\Target\VCS;

use Drutiny\Attribute\AsTarget;
use Drutiny\Target\Target;
use Drutiny\Target\TargetInterface;
use Drutiny\Target\FilesystemInterface;

#[AsTarget(name: 'git')]
class GitTarget extends Target implements FilesystemInterface, GitInterface
{
    use GitTrait;
    protected string $id;

    /**
     * {@inheritdoc}
     */
    public function parse(string $data, ?string $uri = null):TargetInterface
    {
        $data = is_dir($data) ? realpath($data) : $data;
        $this->setLocation($data);
        $this->id = $data;
        $this->setUri($data);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getId():string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getDirectory():string
    {
        return $this->useLocal()->getProperty('vcs.git.location');
    }

    /**
     * Determine if Git repository is local.
     */
    public function gitIsLocal():bool
    {
        return $this->gitIsLocal;
    }
}
