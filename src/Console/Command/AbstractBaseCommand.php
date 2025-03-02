<?php


namespace Drutiny\Console\Command;

use Drutiny\Attribute\Autoload;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract base command.
 *
 * This command helps auto-instansiate typed properties with
 * the service container.
 */
abstract class AbstractBaseCommand extends Command
{

    public function __construct(protected ContainerInterface $container)
    {
        foreach ($this->getLoadableProperties() as $name => $autoload) {
            if (isset($this->{$name})) {
                continue;
            }
            if (!$autoload->early) {
                continue;
            }
            if (!$container->has($autoload->service)) {
                throw new InvalidArgumentException(strtr('Cannot instansiate property "%property" on class "%class" with service "%service" because service does not exist.', [
                    "%property" => $name,
                    "%class" => $this::class,
                    "%service" => $autoload->service,
                ]));
            }
            $this->{$name} = $container->get($autoload->service);
        }
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->instansiate($this->container);
        if (method_exists($this, 'initLanguage')) {
            $this->initLanguage($input);
        }
        return $this->doExecute($input, $output);
    }

    abstract protected function doExecute(InputInterface $input, OutputInterface $output):int;

    /**
     * Instansiate class properties with defaults from the service container.
     */
    protected function instansiate(ContainerInterface $container): void
    {
        foreach ($this->getLoadableProperties() as $name => $autoload) {
            if (isset($this->{$name}) || !$autoload->enabled) {
                continue;
            }

            if (!$container->has($autoload->service)) {
                $container->get(LoggerInterface::class)->warning(strtr('Cannot instansiate property "%property" on class "%class" with service "%service" because service does not exist.', [
                    "%property" => $name,
                    "%class" => $this::class,
                    "%service" => $autoload->service,
                ]));
                continue;
            }
            $this->{$name} = $container->get($autoload->service);
        }
    }

    /**
     * @return \Drutiny\Attribute\Autoload[]
     */
    private function getLoadableProperties(): array
    {
        $reflection = new \ReflectionClass($this::class);
        $properties = $this->getLoadablePropertiesForClass($reflection);
        
        foreach ($reflection->getTraits() as $traitReflection) {
            foreach ($this->getLoadablePropertiesForClass($traitReflection) as $name => $autoload) {
                $properties[$name] = $autoload;
            }
        }
        return $properties;
    }

    /**
     * @return \Drutiny\Attribute\Autoload[]
     */
    private function getLoadablePropertiesForClass(ReflectionClass $reflection): array
    {
        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            if (isset($this->{$property->name})) {
                continue;
            }
            if (!$property->hasType()) {
                continue;
            }
            $type = $property->getType();
            if ($type->isBuiltin()) {
                continue;
            }
            $attributes = $property->getAttributes(Autoload::class);
            $autoload = new Autoload();
            if (count($attributes)) {
                $autoload = $attributes[0]->newInstance();
            }
            if ($autoload->service === null) {
                $autoload = $autoload->with(service: $type->getName());
            }
            $properties[$property->name] = $autoload;
        }
        return $properties;
    }
}
