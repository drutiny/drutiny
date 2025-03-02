<?php

namespace Drutiny\Target;

interface FilesystemInterface extends TargetInterface
{

  /**
   * Return the path to the targeted directory.
   */
    public function getDirectory():string;
}
