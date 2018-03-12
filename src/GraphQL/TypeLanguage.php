<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\GraphQL;

use Prooph\EventMachine\JsonSchema\JsonSchema;

final class TypeLanguage
{
    public const SCOPES = ['input', 'type', 'interface', 'object'];

    public const TYPE_STRING = 'String';
    public const TYPE_INT = 'Int';
    public const TYPE_FLOAT = 'Float';
    public const TYPE_BOOL = 'Boolean';

    private $objectTypes = [];

    private $interfaces = [];

    private $enums = [];

    public static function fromEventMachineDescriptions(
        array $queries,
        array $inputTypes,
        array $queryReturnTypes,
        array $types,
        array $commands = null): string
    {
        $interfaces = [];

        foreach ($types as $typeName => $typeSchema) {
            $interfaces = array_merge($interfaces, self::scanTypeForInterfaces($typeSchema));
        }

        $AST = new self();

        foreach ($types as $typeName => $typeSchema) {
            if (array_key_exists($typeName, $interfaces)) {
                $AST->interfaces[$typeName] = $typeSchema;
                unset($types[$typeName]);
            }
        }

        foreach ($types  as $typeName => $typeSchema) {
            if (JsonSchema::isStringEnum($typeSchema)) {
                $AST->enums[$typeName] = $typeSchema;
                continue;
            }

            if (! JsonSchema::isObjectType($typeSchema)) {
                throw new \RuntimeException("A non object type is registered with name $typeName and schema " . json_encode($typeSchema));
            }

            $AST->objectTypes[$typeName] = $typeSchema;
        }

        $typeLanguage = '';

        foreach ($AST->enums as $enumName => $enumSchema) {
            $typeLanguage .= "\n" . self::convertEnum($enumName, $enumSchema) . "\n";
        }

        foreach ($AST->interfaces as $interfaceName => $interfaceSchema) {
            $typeLanguage .= "\n" . self::convertInterface($interfaceName, $interfaceSchema, $AST) . "\n";
        }

        foreach ($AST->objectTypes as $objectName => $objectType) {
            $typeLanguage .= "\n" . self::convertObject('type', $objectName, $objectType, $AST) . "\n";
        }

        foreach ($inputTypes as $inputTypeName => $inputTypeSchema) {
            $typeLanguage .= "\n" . self::convertObject('input', $inputTypeName, $inputTypeSchema, $AST) . "\n";
        }

        $typeLanguage .= "\ntype Query {\n";

        foreach ($queries as $queryName => $payload) {
            if (! array_key_exists($queryName, $queryReturnTypes)) {
                throw new \RuntimeException("Missing return type for query $queryName");
            }

            if (JsonSchema::isObjectType($queryReturnTypes[$queryName])) {
                throw new \RuntimeException("Return type of query $queryName is of type object. You should register a type and reference it with JsonSchema::typeRef() instead.");
            }

            $typeLanguage .= "  $queryName";

            if (! empty($payload['properties'])) {
                $typeLanguage .= self::convertPayloadToArguments($queryName, $payload, $AST);
            }

            $typeLanguage .= ': ' . self::convertSchemaTypeToGraphQLType('type', "{$queryName}ReturnType", $queryReturnTypes[$queryName], $AST) . "\n";
        }

        $typeLanguage .= "}\n";

        if ($commands) {
            $typeLanguage .= "\ntype Mutation {\n";

            foreach ($commands as $commandName => $payload) {
                $typeLanguage .= "  $commandName";

                if ($payload) {
                    $typeLanguage .= self::convertPayloadToArguments($commandName, $payload, $AST);
                }

                $typeLanguage .= ': ' . self::TYPE_BOOL . "!\n";
            }

            $typeLanguage .= "}\n";
        }

        $typeLanguage .= "\nschema {\n";
        $typeLanguage .= "  query: Query\n";

        if ($commands) {
            $typeLanguage .= "  mutation: Mutation\n";
        }

        $typeLanguage .= "}\n";

        return $typeLanguage;
    }

    private static function convertEnum(string $name, array $enumSchema): string
    {
        if (! JsonSchema::isStringEnum($enumSchema)) {
            throw new \InvalidArgumentException("$name is not a valid enum schema. Only enums with string values are allowed. Got " . json_encode($enumSchema));
        }

        $enum = "enum $name {\n";

        foreach ($enumSchema[JsonSchema::KEYWORD_ENUM] as $val) {
            $enum .= "  $val\n";
        }

        $enum .= '}';

        return $enum;
    }

    private static function convertInterface(string $name, array $schema, TypeLanguage $AST): string
    {
        return self::convertSchemaTypeToGraphQLType('interface', $name, $schema, $AST);
    }

    private static function convertObject(string $scope, string $name, array $schema, TypeLanguage $AST): string
    {
        if (! array_key_exists('properties', $schema) || ! is_array($schema['properties'])) {
            throw new \RuntimeException("Object schema for $name does not contain a valid properties key. Got " . json_encode($schema));
        }

        $implements = '';
        $interfaces = [];

        if ($scope === 'type') {
            $interfaces = array_keys(self::scanTypeForInterfaces($schema));

            if (count($interfaces)) {
                $implements = ' implements ' . implode(', ', $interfaces);
            }
        }

        $obj = "$scope $name".$implements." {\n";

        foreach ($interfaces as $interfaceName) {
            if (! array_key_exists($interfaceName, $AST->interfaces)) {
                throw new \RuntimeException("Interface $interfaceName is not registered. It is referenced in object schema of $name but cannot be found in type definitions.");
            }

            $interfaceSchema = $AST->interfaces[$interfaceName];

            if (! array_key_exists('properties', $interfaceSchema) || ! is_array($interfaceSchema['properties'])) {
                throw new \RuntimeException("Interface schema of $interfaceName does not have a valid properties key.");
            }

            foreach ($interfaceSchema['properties'] as $propName => $propSchema) {
                if (JsonSchema::isObjectType($propSchema)) {
                    throw new \RuntimeException("Cannot convert property $propName of interface $interfaceName. 
                            The property is of type object. 
                            You should register a type instead and reference the type using JsonSchema::typeRef().");
                }

                $obj .= "  $propName: " . self::convertSchemaTypeToGraphQLType($scope, $propName, $propSchema, $AST) . "\n";
            }
        }

        foreach ($schema['properties'] as $propName => $propSchema) {
            if (JsonSchema::isObjectType($propSchema)) {
                throw new \RuntimeException("Cannot convert property $propName of object $name. 
                            The property is of type object. 
                            You should register a type instead and reference the type using JsonSchema::typeRef().");
            }
            $obj .= "  $propName: " . self::convertSchemaTypeToGraphQLType($scope, $propName, $propSchema, $AST) . "\n";
        }

        $obj .= "}\n";

        return $obj;
    }

    private static function convertPayloadToArguments(string $queryName, array $payload, TypeLanguage $AST): string
    {
        $args = '(';

        if (JsonSchema::isObjectType($payload)) {
            if (! array_key_exists('properties', $payload) || ! is_array($payload['properties'])) {
                throw new \RuntimeException("Payload of query $queryName is of type object but has an invalid properties key.");
            }

            foreach ($payload['properties'] as $propName => $propType) {
                if (JsonSchema::isObjectType($propType)) {
                    throw new \RuntimeException("Property $propName of query $queryName is of type object. You should register an input type instead and reference it with JsonSchema::typeRef()");
                }

                $args .= "{$propName}: " . self::convertSchemaTypeToGraphQLType('input', $propName, $propType, $AST) . ', ';
            }

            $args = substr($args, 0, -2);
        } else {
            $args .= 'payload: ' . self::convertSchemaTypeToGraphQLType('input', 'payload', $payload, $AST);
        }

        $args .= ')';

        return $args;
    }

    private static function convertSchemaTypeToGraphQLType(string $scope, string $name, array $schemaType, TypeLanguage $AST): string
    {
        if (! in_array($scope, self::SCOPES)) {
            throw new \InvalidArgumentException("Scope for $name must be one of: " . implode(', ', self::SCOPES));
        }

        $nullable = false;
        $type = null;

        if (array_key_exists('oneOf', $schemaType)) {
            $containsNullType = false;
            $containsTypeRef = false;
            $typeRef = null;
            foreach ($schemaType['oneOf'] as $oneOfType) {
                if (array_key_exists('type', $oneOfType) && $oneOfType['type'] === JsonSchema::TYPE_NULL) {
                    $containsNullType = true;
                }

                if (array_key_exists('$ref', $oneOfType)) {
                    $containsTypeRef = true;
                    $typeRef = $oneOfType;
                }
            }

            if (! $containsNullType || ! $containsTypeRef) {
                throw new \RuntimeException('oneOf schema is only supported for nullable type references. Got ' . json_encode($schemaType));
            }

            $schemaType = $typeRef;
            $nullable = true;
        }

        if (array_key_exists('type', $schemaType)) {
            if (is_array($schemaType['type'])) {
                if (count($schemaType['type']) > 2) {
                    throw new \RuntimeException("Unions are not supported yet but schema type of $name contains more than two types. Got " . json_encode($schemaType));
                }

                foreach ($schemaType['type'] as $t) {
                    if ($t === 'null') {
                        $nullable = true;
                        continue;
                    }

                    $type = $t;
                }
            } else {
                $type = $schemaType['type'];
            }
        }

        if (array_key_exists('$ref', $schemaType)) {
            if ($scope === 'interface' && ! array_key_exists(JsonSchema::extractTypeFromRef($schemaType['$ref']), $AST->enums)) {
                throw new \RuntimeException("Interface $name contains a reference to " . $schemaType['$ref']);
            }

            $type = JsonSchema::extractTypeFromRef($schemaType['$ref']);

            if ($scope === 'input' && (array_key_exists($type, $AST->objectTypes) || array_key_exists($type, $AST->interfaces))) {
                throw new \RuntimeException("An input type must not reference an interface or object type. 
                            Found a reference named $name that points to type $type. 
                            You should register an input type with the name {$type}Input instead.");
            }

            return $type . (($nullable) ? '' : '!');
        }

        if (! array_key_exists('type', $schemaType)) {
            throw new \RuntimeException("Missing type in typeSchema of $name. Got " . json_encode($schemaType));
        }

        if ($scope === 'interface') {
            if (array_key_exists('allOf', $schemaType)) {
                throw new \RuntimeException("Interface $name must not implement another interface. Found allOf key in schema: " . json_encode($schemaType));
            }
        }

        switch ($type) {
            case JsonSchema::TYPE_OBJECT:
                return self::convertObject($scope, $name, $schemaType, $AST);
            case JsonSchema::TYPE_ARRAY:
                $typeStr = self::convertArray($scope, $name, $schemaType, $AST);
                break;
            case JsonSchema::TYPE_STRING:
                $typeStr = self::TYPE_STRING;
                break;
            case JsonSchema::TYPE_INT:
                $typeStr = self::TYPE_INT;
                break;
            case JsonSchema::TYPE_FLOAT:
                $typeStr = self::TYPE_FLOAT;
                break;
            case JsonSchema::TYPE_BOOL:
                $typeStr = self::TYPE_BOOL;
                break;
            default:
                throw new \InvalidArgumentException("Type of $name could not be identified. Got schema: " . json_encode($schemaType));
        }

        if ($scope === 'input' && array_key_exists('default', $schemaType)) {
            $typeStr .= ' = ' . $schemaType['default'];
        }

        return $typeStr . (($nullable) ? '' : '!');
    }

    private static function convertArray(string $scope, string $name, array $schema, TypeLanguage $AST): string
    {
        if (! array_key_exists('items', $schema)) {
            throw new \RuntimeException("Array schema of $name does not contain a valid items key.");
        }

        if (JsonSchema::isObjectType($schema['items'])) {
            throw new \RuntimeException("Cannot convert array schema of $name. The item schema is of type object. 
                        You should register a type for the object and reference it using JsonSchema::typeRef()");
        }

        return '[' . self::convertSchemaTypeToGraphQLType($scope, $name, $schema['items'], $AST) . ']';
    }

    private static function scanTypeForInterfaces(array $typeSchema): array
    {
        if (! array_key_exists('type', $typeSchema)) {
            return [];
        }

        if (JsonSchema::isArrayType($typeSchema)) {
            if (! array_key_exists('items', $typeSchema)) {
                return [];
            }

            return self::scanTypeForInterfaces($typeSchema['items']);
        }

        if (! JsonSchema::isObjectType($typeSchema)) {
            return [];
        }

        $interfaces = [];

        if (array_key_exists('allOf', $typeSchema)) {
            foreach ($typeSchema['allOf'] as $subTypeSchema) {
                if (array_key_exists('$ref', $subTypeSchema)) {
                    $interfaces[JsonSchema::extractTypeFromRef($subTypeSchema['$ref'])] = null;
                }

                if (JsonSchema::isObjectType($subTypeSchema)) {
                    throw new \RuntimeException('An object must only reference interfaces in a allOf definition. Got schema: ' . json_encode($typeSchema));
                }
            }
        }

        if (array_key_exists('properties', $typeSchema)) {
            foreach ($typeSchema['properties'] as $name => $property) {
                if (JsonSchema::isObjectType($property)) {
                    throw new \RuntimeException("Object property $name must not be an object. Turn it into a reference pointing to a type. Found in schema: " . json_encode($typeSchema));
                }

                $interfaces = array_merge($interfaces, self::scanTypeForInterfaces($property));
            }
        }

        return $interfaces;
    }
}
