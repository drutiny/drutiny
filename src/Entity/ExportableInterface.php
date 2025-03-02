<?php

namespace Drutiny\Entity;

interface ExportableInterface
{
  /**
   * Export an object into a serializable format (e.g. array)
   */
    public function export():array;
}
