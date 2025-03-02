<?php

namespace Drutiny\Plugin;

use Drutiny\Attribute\Plugin as PluginAttribute;
use Drutiny\Attribute\PluginField;
use Drutiny\Config\ConfigInterface;
use Drutiny\Plugin;
use Drutiny\Settings;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginCollection implements PluginInterface
{
    protected array $keys;
    protected array $stores;

    public function __construct(
        protected PluginAttribute $attribute,
        ConfigInterface $pluginCredentials,
        ConfigInterface $pluginConfig,
        ConfigInterface $pluginState,
        protected InputInterface $input,
        protected OutputInterface $output,
        protected LoggerInterface $logger,
        protected Settings $settings,
    ) {
        $this->stores = [
            FieldType::CONFIG->key() => $pluginConfig,
            FieldType::CREDENTIAL->key() => $pluginCredentials,
            FieldType::STATE->key() => $pluginState
        ];
        $config_keys = $this->stores[FieldType::CONFIG->key()]->keys();
        $cred_keys = $this->stores[FieldType::CREDENTIAL->key()]->keys();
        $this->keys = array_unique(array_merge($config_keys, $cred_keys));
    }

    public function getKeys():array
    {
        return $this->keys;
    }
    
    public function getAll():array
    {
        return array_combine($this->keys, array_map(fn($key) => $this->get($key), $this->keys));
    }

    public function has(string $key):bool
    {
        return in_array($key, $this->keys);
    }

    public function get(string $key):Plugin
    {
        return $this->doGet($key);
    }

    public function create(string $key):Plugin
    {
        if ($this->has($key)) {
            throw new Exception("$key already exists.");
        }
        return $this->doGet($key, true);
    }

    protected function doGet(string $key, $createIfNotExists = false):Plugin
    {
        if (!$this->has($key) && !$createIfNotExists) {
            throw new Exception("{$this->attribute->name} plugin '$key' does not exist.");
        }
        $reflection = new ReflectionClass($this->attribute->class);
        return $reflection->newInstance(
            attribute: $this->attribute,
            pluginCredentials: $this->stores[FieldType::CREDENTIAL->key()]->load($key),
            pluginConfig: $this->stores[FieldType::CONFIG->key()]->load($key),
            pluginState: $this->stores[FieldType::STATE->key()]->load($key),
            input: $this->input,
            output: $this->output,
            logger: $this->logger,
            settings: $this->settings
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->attribute->name;
    }

    public function getKeyField():PluginField
    {
        return $this->attribute->getField($this->attribute->collectionKey);
    }

    public function getFieldAttributes(): array
    {
        return $this->attribute->getFieldAttributes();
    }

    public function getPluginAttribute():PluginAttribute
    {
        return $this->attribute;
    }

    /**
     * {@inheritdoc}
     */
    public function isInstalled(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden():bool
    {
        return true;
    }
}
