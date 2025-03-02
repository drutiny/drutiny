<?php

namespace Drutiny\Attribute;

use Attribute;
use Drutiny\Plugin\FieldType;
use Drutiny\Plugin\Question;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
#[Autoconfigure(autowire: false)]
class PluginField
{

    /**
     * Add a configurable field to the plugin schema.
     *
     * @param string $name The name of the field.
     * @param string $description A description of the field purpose.
     * @param int $type A constant indicating if the field is a config or credential.
     * @param mixed $default The default value.
     * @param string $data_type A constant representing the data type.
     * @param int $ask A constant depicting the type of question to ask.
     * @param array $choices an array of choices to choose from for FIELD_CHOICE_QUESTION types.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly FieldType $type = FieldType::CONFIG,
        public readonly mixed $default = null,
        public readonly string $validation = 'is_string',
        public readonly Question $ask = Question::DEFAULT,
        public readonly array $choices = []
    ) {
    }
}
