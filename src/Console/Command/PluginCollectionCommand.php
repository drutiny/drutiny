<?php

namespace Drutiny\Console\Command;

use Drutiny\Attribute\PluginField;
use Drutiny\Plugin as DrutinyPlugin;
use Drutiny\Plugin\FieldType;
use Drutiny\Plugin\PluginCollection;
use Drutiny\Plugin\Question;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginCollectionCommand
{

    public function __construct(protected PluginCollection $pluginCollection)
    {
    }

    /**
     * Command generator function for service container.
     */
    public static function getListCommand(PluginCollection $pluginCollection):Command
    {
        $listCommand = new Command($pluginCollection->getName().':list');
        $listCommand->setDescription("List the available configurations for ".$pluginCollection->getName().".");
        $listCommand->addOption(
            name: 'show-credentials',
            shortcut: 's',
            mode: InputOption::VALUE_NONE,
            description: "Print credentials to terminal."
        );
        $listCommand->setCode(function (InputInterface $input, OutputInterface $output) use ($pluginCollection) {
            return (new PluginCollectionCommand($pluginCollection))->list($input, $output);
        });
        return $listCommand;
    }

    /**
     * List the plugins registred within a plugin colleciton.
     */
    public function list(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        foreach ($this->pluginCollection->getAll() as $key => $plugin) {
            $row = [];
            $headers = [];
            foreach ($plugin->getFieldAttributes() as $field) {
                $row[$field->name] = ($field->type == FieldType::CREDENTIAL && $input->getOption('show-credentials') == false) ? '****' :  $plugin->{$field->name};
                $headers[] = $field->name;
            }
            $rows[$key] = $row;
        }
        ksort($rows);

        if (!empty($rows)) {
            $io->table($headers, $rows);
        } else {
            $io->text("There are no plugins setup for ".$this->pluginCollection->getName());
            $io->text("Run ".$this->pluginCollection->getName().":setup to configure a new plugin setting.");
        }
        
        return 0;
    }

    /**
     * Command generator function for service container.
     */
    public static function getAddCommand(PluginCollection $pluginCollection):Command
    {
        $setupCommand = new Command($pluginCollection->getName().':add');
        $setupCommand->setDescription("Add a new configuration entry to ".$pluginCollection->getName().".");
        $setupCommand->addArgument(
            name: $pluginCollection->getPluginAttribute()->collectionKey,
            mode: InputOption::VALUE_REQUIRED,
            description: $pluginCollection->getKeyField()->description
        );
        foreach ($pluginCollection->getFieldAttributes() as $field_name => $field) {
            $setupCommand->addOption($field_name, null, InputOption::VALUE_OPTIONAL, $field->description, $field->default);
        }
        $setupCommand->setCode(function (InputInterface $input, OutputInterface $output) use ($pluginCollection) {
            return (new PluginCollectionCommand($pluginCollection))->add($input, $output);
        });
        return $setupCommand;
    }

    /**
     * Add a plugin entry to a plugin collection.
     */
    public function add(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument($this->pluginCollection->getKeyField()->name);

        if (empty($namespace)) {
            $io->error($this->pluginCollection->getKeyField()->name . ' is a required argument. See --help for more information.');
            return 1;
        }

        if ($this->pluginCollection->has($namespace) && !$io->confirm("$namespace already exists. Override it?")) {
            $io->text("Aborted.");
            return 0;
        }
        $plugin = $this->pluginCollection->has($namespace) ? $this->pluginCollection->get($namespace) : $this->pluginCollection->create($namespace);

        $values = [];
        foreach ($this->pluginCollection->getFieldAttributes() as $field) {
            if ($field->name == $this->pluginCollection->getKeyField()->name) {
                $values[$field->name] = $namespace;
                continue;
            }
            $values[$field->name] = $input->getOption($field->name) ?? $this->setupField($field, $plugin, $io);
        }
        $plugin->saveAs($values);

        $io->success("Plugin '$namespace' has been setup.");
        return 0;
    }

    public static function getDeleteCommand(PluginCollection $pluginCollection):Command
    {
        $deleteCommand = new Command($pluginCollection->getName().':delete');
        $deleteCommand->setDescription("Remove a configuration entry from ".$pluginCollection->getName().".");
        $deleteCommand->addArgument(
            name: $pluginCollection->getPluginAttribute()->collectionKey,
            mode: InputOption::VALUE_REQUIRED,
            description: $pluginCollection->getKeyField()->description
        );
        $deleteCommand->setCode(function (InputInterface $input, OutputInterface $output) use ($pluginCollection) {
            return (new PluginCollectionCommand($pluginCollection))->delete($input, $output);
        });
        return $deleteCommand;
    }

    public function delete(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument($this->pluginCollection->getKeyField()->name);

        if (!$this->pluginCollection->has($namespace)) {
            $io->error("$namespace does not exist.");
            return 1;
        }

        if (!$io->confirm("Are you sure you want to remove $namespace?")) {
            $io->text("Aborted.");
            return 0;
        }

        $this->pluginCollection->get($namespace)->delete();
        $io->success("$namespace has been removed.");
        return 0;
    }

    /**
     * Get user input to get the value of a field.
     */
    protected function setupField(PluginField $field, DrutinyPlugin $plugin, SymfonyStyle $io)
    {
        $extra = ' ';
        $default_value = null;
        if (isset($plugin->{$field->name})) {
            $existing_value = $plugin->{$field->name};
            $extra = "\n<comment>An existing credential exists.\n";
            if ($field->type == FieldType::CONFIG) {
                $extra .= "Existing value: {$existing_value}\n";
            }
            $extra .= "Leave blank to use existing value.</comment>\n";
            $default_value = $plugin->{$field->name};
        }
        $ask = sprintf("%s%s\n<info>[%s]</info>:     ", $extra, ucfirst($field->description), $field->name);
        do {
            $value = match ($field->ask) {
                Question::CHOICE => $io->choice($ask, $field->choices, $default_value),
                Question::CONFIRMATION => $io->confirm("$ask (y/n)?", $default_value ?? true),
                Question::DEFAULT => $io->ask($ask, $default_value)
            };
            if (!call_user_func($field->validation, $value)) {
                $io->error("Input failed validation");
                continue;
            }
            break;
        } while (true);
        return $value;
    }
}
