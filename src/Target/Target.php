<?php

namespace Drutiny\Target;

use Drutiny\Entity\DataBag;
use Drutiny\Entity\Exception\DataNotFoundException;
use Drutiny\LocalCommand;
use Drutiny\Target\Service\ServiceFactory;
use Drutiny\Target\Service\ServiceInterface;
use Drutiny\Target\Transport\LocalTransport;
use Drutiny\Target\Transport\TransportInterface;
use Monolog\Logger;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Process\Process;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

/**
 * Basic function of a Target.
 */
abstract class Target implements \ArrayAccess, TargetInterface
{
    /* @var PropertyAccess */
    protected $propertyAccessor;
    private string $targetName;
    protected TransportInterface $transport;
    protected DataBag $properties;
    protected array $lazyProperties = [];

    public function __construct(
        protected LoggerInterface $logger,
        protected LocalCommand $localCommand,
        protected ServiceFactory $serviceFactory,
        protected EventDispatcher $eventDispatcher
    ) {
        if ($logger instanceof Logger) {
            $this->logger = $logger->withName('target');
        }
        $this->properties = new DataBag();
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableExceptionOnInvalidIndex()
            ->getPropertyAccessor();

        $this->transport = new LocalTransport($this->localCommand);
    }

    /**
     * Load a target from a given ID and optional URI.
     */
    final public function load(string $id, ?string $uri = null):void
    {
        $this->parse($id, $uri);
        $this->finaliseLoad();
    }

    /**
     * Load target by properties instead of by ID.
     */
    final public function loadByProperties(array $properties)
    {
        foreach ($properties as $key => $value) {
            $this[$key] = $value;
        }
        $this->finaliseLoad();
    }

    /**
     * Finalise the loading process.
     */
    private function finaliseLoad()
    {
        $this->transport = $this->loadTransport();
        $event = new GenericEvent('target.load', [
            'target' => $this
        ]);
        $this->eventDispatcher->dispatch($event, $event->getSubject());
        $this->rebuildEnvVars();
    }

    /**
     * Override this method to change the transport the target defaults to.
     */
    protected function loadTransport(): TransportInterface
    {
        return new LocalTransport($this->localCommand);
    }

    /**
     * {@inheritdoc}
     */
    final public function setTargetName(string $name): TargetInterface
    {
        $this->targetName = $name;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    final public function getTargetName(): string
    {
        return $this->targetName;
    }

    /**
     * Set the transport for sending commands to the target.
     */
    final public function setTransport(TransportInterface $transport):self
    {
        $this->transport = $transport;
        return $this;
    }

    /**
     * Get a target service (e.g. drush).
     */
    final public function getService(string $id):ServiceInterface
    {
        $service = $this->serviceFactory->get($id, $this->transport);
        $this->configureService($service);
        return $service;
    }

    /**
     * Allow inheriting Target classes to configure services.
     */
    protected function configureService(ServiceInterface $service):void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setUri(string $uri):TargetInterface
    {
        $this->setProperty('domain', parse_url($uri, PHP_URL_HOST) ?? $uri);
        return $this->setProperty('uri', $uri);
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): string
    {
        return $this->getProperty('uri');
    }

    /**
     * Backwards compatible support for run() method. Use execute() instead.
     *
     * @deprecated
     */
    public function run(string $cmd, ?callable $preProcess = null, int $ttl = 3600)
    {
        $this->logger->warning("Deprecated method run() called on Target. Use execute() instead.");
        return $this->transport->send(Process::fromShellCommandline($cmd), $preProcess);
    }

    /**
     * Wrapper function around transport send method.
     */
    public function execute(Process $command, ?callable $preProcess = null)
    {
        return $this->transport->send($command, $preProcess);
    }

    /**
     * Set a property.
     */
    public function setProperty($key, $value):TargetInterface
    {
        $this->confirmPropertyPath($key);

        // If the property is already a DataBag object and is attempting to be
        // replaced with a non-DataBag value, then throw an exception as this
        // will loose target data and create problems accessing deeper
        // property references.
        if ((!($value instanceof DataBag)) && $this->hasProperty($key) && ($this->getProperty($key) instanceof DataBag)) {
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new TargetPropertyException("Property '$key' contains multiple dimensions of data and cannot be overriden with data of type: ". $type);
        }
        $this->propertyAccessor->setValue($this->properties, $key, $value);
        return $this;
    }

    public function registerLazyProperty($name, callable $value): void
    {
        if ($this->hasProperty($name)) {
            throw new TargetPropertyException("Cannot register lazy property '$name'. Property already exists.");
        }
        $this->lazyProperties[$name] = $value;
    }

    /**
     * Rebuild the environment variables from the target properties.
     */
    protected function rebuildEnvVars():void
    {
        foreach ($this->getPropertyList() as $key) {
            try {
                $value = $this->getProperty($key);
            }
            // TODO: Fix bug that generates a DataNotFoundException from a listed property.
            catch (DataNotFoundException $e) {
                $this->logger->warning("$key wasn't a valid property: ".$e->getMessage());
            }

            if ((is_object($value) && !method_exists($key, '__toString'))) {
                continue;
            }
            $this->localCommand->setEnvVar($key, $value);
        }
    }

    /**
     * Ensure the property pathway exists.
     */
    protected function confirmPropertyPath($path)
    {
        // Handle top level properties.
        if (strpos($path, '.') === false) {
            return $this;
        }

        $bits = explode('.', $path);
        $total_bits = count($bits);
        $new_paths = [];
        do {
            $pathway = implode('.', $bits);

            // Do not create the $path pathway as setProperty will do this for us.
            if ($pathway == $path) {
                continue;
            }

            if (empty($pathway)) {
                break;
            }

            // If the pathway doesn't exist yet, create it as a new DataBag.
            if ($this->hasProperty($pathway)) {
                break;
            }

            // If the parent is a DataBag then the pathway is settable.
            if ($total_bits == count($bits) && $this->getParentProperty($pathway) instanceof DataBag) {
                break;
            }
            $new_paths[] = $pathway;
        } while (array_pop($bits));

        // Create all the DataBag objects required to support this pathway.
        foreach (array_reverse($new_paths) as $pathway) {
            $this->setProperty($pathway, new DataBag);
        }
        return $this;
    }

    /**
     * Find the parent value.
     */
    private function getParentProperty($path)
    {
        if (strpos($path, '.') === false) {
            return false;
        }
        $bits = explode('.', $path);
        array_pop($bits);
        $path = implode('.', $bits);
        return $this->hasProperty($path) ? $this->getProperty($path) : false;
    }

    /**
     * Get a set property.
     *
     * @throws NoSuchIndexException
     */
    public function getProperty($key):mixed
    {
        try {
            return $this->propertyAccessor->getValue($this->properties, $key);
        } catch (NoSuchIndexException $e) {
            if (isset($this->lazyProperties[$key])) {
                $this->setProperty($key, $value = $this->lazyProperties[$key]($this));
                // Unload lazy property now it has been fufilled.
                unset($this->lazyProperties[$key]);
                
                return $value;
            }
            throw $e;
        }
    }

    /**
     *  Alias for getProperty().
     */
    public function __get($key)
    {
        return $this->getProperty($key);
    }

    /**
     * Get a list of properties available.
     */
    public function getPropertyList():array
    {
        $paths = $this->getDataPaths($this->properties);
        sort($paths);
        return $paths;
    }

    /**
     * Traverse DataBags to obtain a list of property pathways.
     */
    private function getDataPaths(Databag $bag, $prefix = '')
    {
        $keys = [];
        foreach ($bag->all() as $key => $value) {
            // Periods are reserved characters and cannot be used for DataPaths.
            if (strpos($key, '.') !== false) {
                continue;
            }
            $keys[] = $prefix.$key;
            if ($value instanceof Databag) {
                $keys = array_merge($this->getDataPaths($value, $prefix.$key.'.'), $keys);
            }
        }
        return $keys;
    }

    /**
     * Check a property path exists.
     */
    public function hasProperty($key):bool
    {
        if (isset($this->lazyProperties[$key])) {
            return true;
        }
        try {
            $this->propertyAccessor->getValue($this->properties, $key);
            return true;
        } catch (NoSuchIndexException $e) {
            return false;
        } catch (DataNotFoundException $e) {
            return false;
        } catch (NoSuchPropertyException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            throw new \Exception(__CLASS__ . ' does not support numeric indexes as properties.');
        }
        $this->setProperty($offset, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        return $this->hasProperty($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        throw new \Exception("Cannot unset $offset. Properties cannot be removed. Please set to null instead.");
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset): mixed
    {
        return $this->hasProperty($offset) ? $this->getProperty($offset) : null;
    }

    abstract public function parse(string $data, ?string $uri = null): TargetInterface;
}
