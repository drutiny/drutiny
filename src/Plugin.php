<?php

namespace Drutiny;

use Drutiny\Attribute\Plugin as PluginAttribute;
use Drutiny\Config\ConfigInterface;
use Drutiny\Plugin\FieldType;
use Drutiny\Plugin\PluginInterface;
use Drutiny\Plugin\PluginRequiredException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Plugin implements PluginInterface
{
    private array $stores;
    private ?bool $isInstalled;

    public function __construct(
        ConfigInterface $pluginConfig,
        ConfigInterface $pluginCredentials,
        ConfigInterface $pluginState,
        protected InputInterface $input,
        protected OutputInterface $output,
        protected PluginAttribute $attribute,
        protected Settings $settings,
        protected LoggerInterface $logger
    ) {
        // Set the name from the AsPlugin attribute.
        $this->stores[FieldType::CONFIG->key()] = $pluginConfig;
        $this->stores[FieldType::CREDENTIAL->key()] = $pluginCredentials;
        $this->stores[FieldType::STATE->key()] = $pluginState;
        $this->input = $input;
        $this->output = $output;
        $this->configure();
    }

    final public function getName():string
    {
        return $this->attribute->name;
    }

    /**
     * Get an array of field attributes keyed by field name.
     */
    final public function getFieldAttributes():array
    {
        return $this->attribute->getFieldAttributes();
    }

    /**
     * Get a plugin field value or default value.
     */
    final public function __get($name):mixed
    {
        if (!$this->isInstalled()) {
          // Use the default value if provided.
            if (($default = $this->attribute->getField($name)->default) !== null) {
                return $default;
            }
            throw new PluginRequiredException("{$this->attribute->name} is not installed. Please run 'plugin:setup {$this->attribute->name}' to configure.");
        }
        $field_type = $this->attribute->getField($name)->type;
        $store = $this->stores[$field_type->key()];
        return $store[$name] ?? $this->attribute->getField($name)->default;
    }

    final public function __isset($name)
    {
        $field_type = $this->attribute->getField($name)->type;
        $store = $this->stores[$field_type->key()];
        return isset($store[$name]);
    }

    /**
     * Callback for extending classes to action something on construction.
     */
    protected function configure()
    {
    }

    /**
     * Determines if the plugin is installed or not.
     */
    final public function isInstalled():bool
    {
        if (isset($this->isInstalled)) {
            return $this->isInstalled;
        }
        $this->isInstalled = false;
        foreach ($this->getFieldAttributes() as $name => $field_info) {
            $this->isInstalled = $this->isInstalled || $this->__isset($name);
        }
        return $this->isInstalled;
    }

    final public function isHidden():bool
    {
        return $this->attribute->hidden;
    }

    public function saveAs(array $values):void
    {
        foreach ($this->getFieldAttributes() as $field) {
            if (!isset($values[$field->name])) {
                continue;
            }
            $this->stores[$field->type->key()][$field->name] = $values[$field->name];
        }
        foreach ($this->stores as $store) {
            $store->save();
        }
        $this->isInstalled = null;
    }

    /**
     * Delete the configuration from the plugin storage.
     * @return void
     */
    public function delete():void
    {
        foreach ($this->stores as $store) {
            $store->delete();
        }
    }
}
