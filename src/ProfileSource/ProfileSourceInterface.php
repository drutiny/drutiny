<?php

namespace Drutiny\ProfileSource;

use Drutiny\Profile;
use Drutiny\SourceInterface;

/**
 * Provide policies for Drutiny to use.
 */
interface ProfileSourceInterface extends SourceInterface
{
    /**
     * Load a Drutiny\Policy object.
     *
     * @param array $definition
     *  A definition array generated by PolicySourceInterface::getList().
     */
    public function load(array $definition):Profile;
}
