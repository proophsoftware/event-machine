<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\JsonSchema;

use Prooph\EventMachine\JsonSchema\Type\ArrayType;
use Prooph\EventMachine\JsonSchema\Type\BoolType;
use Prooph\EventMachine\JsonSchema\Type\EmailType;
use Prooph\EventMachine\JsonSchema\Type\EnumType;
use Prooph\EventMachine\JsonSchema\Type\FloatType;
use Prooph\EventMachine\JsonSchema\Type\IntType;
use Prooph\EventMachine\JsonSchema\Type\ObjectType;
use Prooph\EventMachine\JsonSchema\Type\StringType;
use Prooph\EventMachine\JsonSchema\Type\TypeRef;
use Prooph\EventMachine\JsonSchema\Type\UuidType;

final class JsonSchema
{
    public const DEFINITIONS = 'definitions';

    public const TYPE_STRING = 'string';
    public const TYPE_INT = 'integer';
    public const TYPE_FLOAT = 'number';
    public const TYPE_BOOL = 'boolean';
    public const TYPE_ARRAY = 'array';
    public const TYPE_OBJECT = 'object';
    public const TYPE_NULL = "null";

    public const KEYWORD_ENUM = 'enum';

    public static function schemaFromScalarPhpType(string $type, bool $nullable): Type
    {
        switch ($type) {
            case 'string':
                $schema = self::string();
                break;
            case 'int':
                $schema = self::integer();
                break;
            case 'float':
                $schema = self::float();
                break;
            case 'bool':
                $schema = self::boolean();
                break;
            default:
                throw new \RuntimeException("Invalid scalar PHP type given. Got $type");
        }

        if($nullable) {
            $schema = self::nullOr($schema);
        }

        return $schema;
    }

    public static function object(array $requiredProps, array $optionalProps = [], $additionalProperties = false): ObjectType
    {
        return new ObjectType($requiredProps, $optionalProps, $additionalProperties);
    }

    public static function array(Type $itemSchema, array $validation = null): ArrayType
    {
        return new ArrayType($itemSchema, $validation);
    }

    public static function string(array $validation = null): StringType
    {
        return new StringType($validation);
    }

    public static function email(): EmailType
    {
        return new EmailType();
    }

    public static function uuid(): UuidType
    {
        return new UuidType();
    }

    public static function integer(array $validation = null): IntType
    {
        return new IntType($validation);
    }

    public static function float(array $validation = null): FloatType
    {
        return new FloatType($validation);
    }

    public static function boolean(): BoolType
    {
        return new BoolType();
    }

    public static function enum(array $entries): EnumType
    {
        return new EnumType(...$entries);
    }

    public static function nullOr(Type $schema): Type
    {
        return $schema->asNullable();
    }

    public static function implementTypes(ObjectType $schema, string ...$types): ObjectType
    {
        foreach ($types as $typeName) {
            $schema = $schema->withImplementedType(new TypeRef($typeName));
        }

        return $schema;
    }

    public static function typeRef(string $typeName): TypeRef
    {
        return new TypeRef($typeName);
    }

    public static function isArrayType(array $typeSchema): bool
    {
        return self::isType('array', $typeSchema);
    }

    public static function isObjectType(array $typeSchema): bool
    {
        return self::isType('object', $typeSchema);
    }

    public static function isStringEnum(array $typeSchema): bool
    {
        if(!array_key_exists(self::KEYWORD_ENUM, $typeSchema)) {
            return false;
        }

        foreach ($typeSchema[self::KEYWORD_ENUM] as $val) {
            if(!is_string($val)) {
                return false;
            }
        }

        return true;
    }

    public static function isType(string $type, array $typeSchema): bool
    {
        if(array_key_exists('type', $typeSchema)) {
            if(is_array($typeSchema['type'])) {
                foreach ($typeSchema['type'] as $possibleType) {
                    if($possibleType === $type) {
                        return true;
                    }
                }
            } else if (is_string($typeSchema['type'])) {
                return $typeSchema['type'] === $type;
            }
        }

        return false;
    }

    public static function extractTypeFromRef(string $ref): string
    {
        return str_replace('#/' . JsonSchema::DEFINITIONS . '/', '', $ref);
    }

    public static function assertAllInstanceOfType(array $types): void
    {
        foreach ($types as $key => $type) {
            if(!$type instanceof Type) {
                throw new \InvalidArgumentException(
                    "Invalid type at key $key. Type must implement Prooph\EventMachine\JsonSchema\Type. Got "
                    . ((is_object($type)? get_class($type) : gettype($type))));
            }
        }
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
