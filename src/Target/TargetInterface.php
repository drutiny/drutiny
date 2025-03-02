<?php

namespace Drutiny\Target;

use Drutiny\Target\Service\ServiceInterface;
use Symfony\Component\Process\Process;

/**
 * Definition of a Target.
 */
interface TargetInterface
{

  /**
   * Parse the target data passed in.
   * @param $data string to parse.
   */
    public function load(string $id, ?string $uri = null):void;

    /**
     * Run a command through the ExecutionService.
     *
     * @deprecated use Drutiny\Target\TargetInterface::execute() instead.
     */
    public function run(string $cmd, callable $preProcess, int $ttl = 3600);

    /**
     * Send a process to be executed on the target.
     */
    public function execute(Process $process, ?callable $processor = null);

    /**
     * Return the target identifier.
     */
    public function getId():string;

    /**
     * Set target reference.
     */
    public function setTargetName(string $name):TargetInterface;

    /**
     * Get target reference name.
     */
    public function getTargetName():string;

    /**
     * Get the URI for the Target.
     */
    public function getUri(): string;

    /**
     * Get the URI for the Target.
     */
    public function setUri(string $uri): TargetInterface;

    /**
     * Get a list of properties available.
     */
    public function getPropertyList(): array;

     /**
     * Get a set property.
     *
     * @throws NoSuchIndexException
     */
    public function getProperty($key):mixed;

    /**
     * Check a property path exists.
     */
    public function hasProperty($key):bool;

    /**
     * Set a property.
     */
    public function setProperty($key, $value):self;

    /**
     * Get a Service.
     */
    public function getService(string $id):ServiceInterface;
}
