<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\JsonSchema;


final class JsonSchema
{
    public static function object(array $requiredProps, array $optionalProps = [], $additionalProperties = false): array
    {
        return [
            'type' => 'object',
            'required' => array_keys($requiredProps),
            'additionalProperties' => $additionalProperties,
            'properties' => array_merge($requiredProps, $optionalProps)
        ];
    }

    public static function metaSchema(): array
    {
        static $schema = [
            '$schema' => 'http://json-schema.org/draft-06/schema#',
            '$id' => 'http://json-schema.org/draft-06/schema#',
            'title' => 'Core schema meta-schema',
            'definitions' =>
                array (
                    'schemaArray' =>
                        array (
                            'type' => 'array',
                            'minItems' => 1,
                            'items' =>
                                array (
                                    '$ref' => '#',
                                ),
                        ),
                    'nonNegativeInteger' =>
                        array (
                            'type' => 'integer',
                            'minimum' => 0,
                        ),
                    'nonNegativeIntegerDefault0' =>
                        array (
                            'allOf' =>
                                array (
                                    0 =>
                                        array (
                                            '$ref' => '#/definitions/nonNegativeInteger',
                                        ),
                                    1 =>
                                        array (
                                            'default' => 0,
                                        ),
                                ),
                        ),
                    'simpleTypes' =>
                        array (
                            'enum' =>
                                array (
                                    0 => 'array',
                                    1 => 'boolean',
                                    2 => 'integer',
                                    3 => 'null',
                                    4 => 'number',
                                    5 => 'object',
                                    6 => 'string',
                                ),
                        ),
                    'stringArray' =>
                        array (
                            'type' => 'array',
                            'items' =>
                                array (
                                    'type' => 'string',
                                ),
                            'uniqueItems' => true,
                            'default' =>
                                array (
                                ),
                        ),
                ),
            'type' =>
                array (
                    0 => 'object',
                    1 => 'boolean',
                ),
            'properties' =>
                array (
                    '$id' =>
                        array (
                            'type' => 'string',
                            'format' => 'uri-reference',
                        ),
                    '$schema' =>
                        array (
                            'type' => 'string',
                            'format' => 'uri',
                        ),
                    '$ref' =>
                        array (
                            'type' => 'string',
                            'format' => 'uri-reference',
                        ),
                    'title' =>
                        array (
                            'type' => 'string',
                        ),
                    'description' =>
                        array (
                            'type' => 'string',
                        ),
                    'default' =>
                        array (
                        ),
                    'examples' =>
                        array (
                            'type' => 'array',
                            'items' =>
                                array (
                                ),
                        ),
                    'multipleOf' =>
                        array (
                            'type' => 'number',
                            'exclusiveMinimum' => 0,
                        ),
                    'maximum' =>
                        array (
                            'type' => 'number',
                        ),
                    'exclusiveMaximum' =>
                        array (
                            'type' => 'number',
                        ),
                    'minimum' =>
                        array (
                            'type' => 'number',
                        ),
                    'exclusiveMinimum' =>
                        array (
                            'type' => 'number',
                        ),
                    'maxLength' =>
                        array (
                            '$ref' => '#/definitions/nonNegativeInteger',
                        ),
                    'minLength' =>
                        array (
                            '$ref' => '#/definitions/nonNegativeIntegerDefault0',
                        ),
                    'pattern' =>
                        array (
                            'type' => 'string',
                            'format' => 'regex',
                        ),
                    'additionalItems' =>
                        array (
                            '$ref' => '#',
                        ),
                    'items' =>
                        array (
                            'anyOf' =>
                                array (
                                    0 =>
                                        array (
                                            '$ref' => '#',
                                        ),
                                    1 =>
                                        array (
                                            '$ref' => '#/definitions/schemaArray',
                                        ),
                                ),
                            'default' =>
                                array (
                                ),
                        ),
                    'maxItems' =>
                        array (
                            '$ref' => '#/definitions/nonNegativeInteger',
                        ),
                    'minItems' =>
                        array (
                            '$ref' => '#/definitions/nonNegativeIntegerDefault0',
                        ),
                    'uniqueItems' =>
                        array (
                            'type' => 'boolean',
                            'default' => false,
                        ),
                    'contains' =>
                        array (
                            '$ref' => '#',
                        ),
                    'maxProperties' =>
                        array (
                            '$ref' => '#/definitions/nonNegativeInteger',
                        ),
                    'minProperties' =>
                        array (
                            '$ref' => '#/definitions/nonNegativeIntegerDefault0',
                        ),
                    'required' =>
                        array (
                            '$ref' => '#/definitions/stringArray',
                        ),
                    'additionalProperties' =>
                        array (
                            '$ref' => '#',
                        ),
                    'definitions' =>
                        array (
                            'type' => 'object',
                            'additionalProperties' =>
                                array (
                                    '$ref' => '#',
                                ),
                            'default' =>
                                array (
                                ),
                        ),
                    'properties' =>
                        array (
                            'type' => 'object',
                            'additionalProperties' =>
                                array (
                                    '$ref' => '#',
                                ),
                            'default' =>
                                array (
                                ),
                        ),
                    'patternProperties' =>
                        array (
                            'type' => 'object',
                            'additionalProperties' =>
                                array (
                                    '$ref' => '#',
                                ),
                            'default' =>
                                array (
                                ),
                        ),
                    'dependencies' =>
                        array (
                            'type' => 'object',
                            'additionalProperties' =>
                                array (
                                    'anyOf' =>
                                        array (
                                            0 =>
                                                array (
                                                    '$ref' => '#',
                                                ),
                                            1 =>
                                                array (
                                                    '$ref' => '#/definitions/stringArray',
                                                ),
                                        ),
                                ),
                        ),
                    'propertyNames' =>
                        array (
                            '$ref' => '#',
                        ),
                    'const' =>
                        array (
                        ),
                    'enum' =>
                        array (
                            'type' => 'array',
                            'minItems' => 1,
                            'uniqueItems' => true,
                        ),
                    'type' =>
                        array (
                            'anyOf' =>
                                array (
                                    0 =>
                                        array (
                                            '$ref' => '#/definitions/simpleTypes',
                                        ),
                                    1 =>
                                        array (
                                            'type' => 'array',
                                            'items' =>
                                                array (
                                                    '$ref' => '#/definitions/simpleTypes',
                                                ),
                                            'minItems' => 1,
                                            'uniqueItems' => true,
                                        ),
                                ),
                        ),
                    'format' =>
                        array (
                            'type' => 'string',
                        ),
                    'allOf' =>
                        array (
                            '$ref' => '#/definitions/schemaArray',
                        ),
                    'anyOf' =>
                        array (
                            '$ref' => '#/definitions/schemaArray',
                        ),
                    'oneOf' =>
                        array (
                            '$ref' => '#/definitions/schemaArray',
                        ),
                    'not' =>
                        array (
                            '$ref' => '#',
                        ),
                ),
            'default' =>
                array (
                ),

        ];

        return $schema;
    }

    private function __construct()
    {
        //static class only
    }
}
