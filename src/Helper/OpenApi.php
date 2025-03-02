<?php

namespace Drutiny\Helper;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi as SpecOpenApi;
use cebe\openapi\spec\Schema;
use cebe\openapi\Writer;
use DateTimeInterface;
use Drutiny\Attribute\ArrayType;
use Drutiny\Attribute\Description;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Policy;
use Drutiny\Policy\Chart;
use Drutiny\Policy\Dependency;
use Drutiny\Policy\Tag;
use Drutiny\Profile;
use Drutiny\Profile\FormatDefinition;
use Drutiny\Profile\PolicyDefinition;
use Drutiny\Report\Report;
use Drutiny\Target\TargetInterface;
use ReflectionClass;
use ReflectionEnum;
use ReflectionProperty;

class OpenApi
{

    public static function getFilename():string
    {
        return realpath(__DIR__ . '/../../openapi-spec.json');
    }

    public static function writeSpec():string
    {
        Writer::writeToJsonFile(self::buildInstance(), self::getFilename());
        return self::getFilename();
    }

    public static function getInstance():SpecOpenApi
    {
        if (!file_exists(self::getFilename())) {
            return self::buildInstance();
        }
        return Reader::readFromJsonFile(self::getFilename());
    }

    public static function buildInstance():SpecOpenApi
    {
        $openapi = [
            'openapi' => '3.0.2',
            'info' => [
                'title' => 'Drutiny API',
                'version' => '3.6.0',
            ],
            'paths' => [],
            'components' => [
                'schemas' => []
            ]
        ];
        
        $schemas = [
            Policy::class => 'Policy',
            Profile::class => 'Profile',
            TargetInterface::class => 'Target',
            Report::class => 'Report',
            AuditResponse::class => 'Result',
            Tag::class => 'Tag',
            Chart::class => 'Chart',
            Dependency::class => 'Dependency',
            PolicyDefinition::class => 'PolicyDefinition',
            FormatDefinition::class => 'FormatDefinition',
        ];
        
        foreach ($schemas as $class => $name) {
            $openapi['components']['schemas'][$name] = self::buildClass(new ReflectionClass($class), $schemas);
        }

        $api = new SpecOpenApi($openapi);
        $api->validate();
        return $api;
    }

    public static function buildClass(ReflectionClass $reflection, array $references = [])
    {
        $schema = [
            'type' => 'object',
            'properties' => self::buildClassProperties($reflection, $references),
            'x-name' => $references[$reflection->name] ?? ''
        ];
        $schema['required'] = array_keys(array_filter($schema['properties'], fn($p) => !isset($p['default'])));
        $schema['additionalProperties'] = $reflection->name == TargetInterface::class;

        return new Schema($schema);
    }

    public static function buildClassProperties(ReflectionClass $reflection, array $references = []):array
    {
        $properties = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $properties[$property->name] = self::buildProperty($property, $references);
        }
        return $properties;
    }

    public static function buildProperty(ReflectionProperty $property, array $references = [])
    {
        $schema = [
            'title' => $property->name,
            'type' => class_exists($property->getType()) || interface_exists($property->getType()) ? 'object' : match ((string) $property->getType()) {
                'int' => 'number',
                'float' => 'number',
                'bool' => 'boolean',
                'array' => 'array',
                default => 'string'
            },
            'readOnly' => $property->isReadOnly(),
            'nullable' => $property->getType()->allowsNull()
        ];

        if (class_exists($property->getType()) || interface_exists($property->getType())) {
            $schema['x-class'] = (string) $property->getType();
            if (isset($references[$schema['x-class']])) {
                return ['$ref' => '#/components/schemas/'.$references[$schema['x-class']]];
            }
        }

        if (enum_exists($property->getType())) {
            $reflection = new ReflectionEnum((string) $property->getType());
            if ($type = $reflection->getBackingType()) {
                $schema['type'] = $type->getName() == 'int' ? 'integer' : 'string';
            }
            $schema['enum'] = array_map(fn($e) => $e->getValue(), (new ReflectionEnum((string) $property->getType()))->getCases());
        }

        $description = $property->getAttributes(Description::class);
        if (!empty($description)) {
            $schema['description'] = $description[0]->newInstance()->value;
        }

        if ($property->hasDefaultValue()) {
            $schema['default'] = $property->getDefaultValue();
        }

        if ($schema['type'] == 'object') {
            $reflection = new ReflectionClass($property->getType()->getName());
            $schema['properties'] = self::buildClassProperties($reflection);
            if ($reflection->implementsInterface(DateTimeInterface::class)) {
                $schema['type'] = 'string';
                $schema['format'] = 'date-time';
                unset($schema['properties']);
            }
        } elseif (($schema['type'] == 'array') && !empty($attribute = $property->getAttributes(ArrayType::class))) {
            $type = $attribute[0]->newInstance();
            $schema['type'] = $type->type == 'keyed' ? 'object' : 'array';
            if ($type->of !== null) {
                $schema[$type->type == 'keyed' ? 'additionalProperties' : 'items']['$ref'] = '#/components/schemas/'.$references[$type->of] ?? $type->of;
            } elseif ($schema['type'] == 'object') {
                $schema['additionalProperties'] = true;
            }
        }

        return $schema;
    }

    public static function convertNamespace($namespace)
    {
        $bits = explode('\\', $namespace);
        array_shift($bits);
        return implode($bits);
    }
}
