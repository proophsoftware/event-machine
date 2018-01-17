<?php

declare(strict_types = 1);

namespace Prooph\EventMachine\Data;

use Prooph\EventMachine\JsonSchema\JsonSchema;

trait ImmutableRecordLogic
{
    /**
     * @var array
     */
    private static $__propTypeMap;

    /**
     * @var array
     */
    private static $__schema;

    public static function type(): string
    {
        return self::convertClassToTypeName(get_called_class());
    }

    public static function schema(): array
    {
        return self::generateSchemaFromPropTypeMap();
    }

    /**
     * @param array $recordData
     * @return self
     */
    public static function fromRecordData(array $recordData)
    {
        return new self($recordData);
    }

    /**
     * @param array $nativeData
     * @return self
     */
    public static function fromArray(array $nativeData)
    {
        return new self(null, $nativeData);
    }

    private function __construct(array $recordData = null, array $nativeData = null)
    {
        if(null === self::$__propTypeMap) {
            self::$__propTypeMap = self::buildPropTypeMap();
        }

        if($recordData) {
            $this->setRecordData($recordData);
        }

        if($nativeData) {
            $this->setNativeData($nativeData);
        }

        $this->assertAllNotNull();
    }

    /**
     * @param array $recordData
     * @return self
     */
    public function with(array $recordData)
    {
        $copy = clone $this;
        $copy->setRecordData($recordData);
        return $copy;
    }

    public function toArray(): array
    {
        $nativeData = [];

        foreach (self::$__propTypeMap as $key => [$type]) {
            switch ($type) {
                case 'string':
                case 'int':
                case 'float':
                case 'bool':
                case 'array':
                    $nativeData[$key] = $this->{$key};
                    break;
                default:
                    $nativeData[$key] = $this->voTypeToNative($this->{$key}, $key, $type);
            }
        }

        return $nativeData;
    }

    private function setRecordData(array $recordData)
    {
        foreach ($recordData as $key => $value) {
            $this->assertType($key, $value);
            $this->{$key} = $value;
        }
    }

    private function setNativeData(array $nativeData)
    {
        $recordData = [];

        foreach ($nativeData as $key => $val) {
            if(!isset(self::$__propTypeMap[$key])) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid property passed to Record %s. Got property with key ' . $key,
                    get_called_class()
                ));
            }

            [$type] = self::$__propTypeMap[$key];

            switch ($type) {
                case 'string':
                case 'int':
                case 'float':
                case 'bool':
                case 'array':
                    $recordData[$key] = $val;
                    break;
                default:
                    $recordData[$key] = $this->fromType($val, $type);
            }
        }

        $this->setRecordData($recordData);
    }

    private function assertAllNotNull()
    {
        foreach (array_keys(self::$__propTypeMap) as $key) {
            if(null === $this->{$key}) {
                throw new \InvalidArgumentException(sprintf(
                    'Missing record data for key %s of record %s.',
                    $key,
                    __CLASS__
                ));
            }
        }
    }

    private function assertType(string $key, $value)
    {
        if(!isset(self::$__propTypeMap[$key])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid property passed to Record %s. Got property with key ' . $key,
                __CLASS__
            ));
        }

        [$type] = self::$__propTypeMap[$key];

        switch ($type) {
            case 'string':
                $isType = is_string($value);
                break;
            case 'int':
                $isType = is_int($value);
                break;
            case 'float':
                $isType = is_float($value);
                break;
            case 'bool':
                $isType = is_bool($value);
                break;
            case 'array':
                $isType = is_array($value);
                break;
            default:
                $isType = $value instanceof $type;
        }

        if(!$isType) {
            throw new \InvalidArgumentException(sprintf(
                'Record %s data contains invalid value for property %s. Expected type is %s. Got type %s.',
                get_called_class(),
                $key,
                $type,
                (is_object($value)
                    ? get_class($value)
                    : gettype($value))
            ));
        }
    }

    private static function buildPropTypeMap()
    {
        $refObj = new \ReflectionClass(__CLASS__);

        $props = $refObj->getProperties();

        $propTypeMap = [];

        foreach ($props as $prop) {
            if($prop->getName() === '__propTypeMap' || $prop->getName() === '__schema') {
                continue;
            }

            if(!$refObj->hasMethod($prop->getName())) {
                throw new \RuntimeException(
                    sprintf(
                        'No method found for Record property %s of %s that has the same name.',
                        $prop->getName(),
                        __CLASS__
                    )
                );
            }

            $method = $refObj->getMethod($prop->getName());

            if(!$method->hasReturnType()) {
                throw new \RuntimeException(
                    sprintf(
                        'Method %s of Record %s does not have a return type',
                        $method->getName(),
                        __CLASS__
                    )
                );
            }

            $type = (string)$method->getReturnType();

            $propTypeMap[$prop->getName()] = [$type, self::isScalarType($type), $method->getReturnType()->allowsNull()];
        }

        return $propTypeMap;
    }

    private static function isScalarType(string $type): bool
    {
        switch ($type) {
            case 'string':
            case 'int':
            case 'float':
            case 'bool':
                return true;
            default:
                return false;
        }
    }

    private function fromType($value, string $type)
    {
        if(!class_exists($type)) {
            throw new \RuntimeException("Type class $type not found");
        }

        switch (gettype($value)) {
            case 'array':
                return $type::fromArray($value);
            case 'string':
                return $type::fromString($value);
            case 'integer':
                return $type::fromInt($value);
            case 'float':
            case 'double':
                return $type::fromFloat($value);
            case 'boolean':
                return $type::fromBool($value);
            default:
                throw new \RuntimeException("Cannot convert value to $type, because native type of value is not supported. Got " . gettype($value));
        }
    }

    private function voTypeToNative($value, string $key, string $type)
    {
        if(method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        if(method_exists($value, 'toString')) {
            return $value->toString();
        }

        if(method_exists($value, 'toInt')) {
            return $value->toInt();
        }

        if(method_exists($value, 'toFloat')) {
            return $value->toFloat();
        }

        if(method_exists($value, 'toBool')) {
            return $value->toBool();
        }

        throw new \RuntimeException("Cannot convert property $key to its native counterpart. Missing to{nativeType}() method in the type class $type.");
    }

    /**
     * @param array $arrayPropTypeMap Map of array property name to array item type
     * @return array
     */
    private static function generateSchemaFromPropTypeMap(array $arrayPropTypeMap = []): array
    {
        if(null === self::$__propTypeMap) {
            self::$__propTypeMap = self::buildPropTypeMap();
        }

        if(null === self::$__schema) {
            $props = [];

            foreach (self::$__propTypeMap as $prop => [$type, $isScalar, $isNullable]) {
                if($isScalar) {
                    $props[$prop] = JsonSchema::schemaFromScalarPhpType($type, $isNullable);
                    continue;
                }

                if($type === "array") {
                    if(!array_key_exists($prop, $arrayPropTypeMap)) {
                        throw new \RuntimeException("Missing array item type in array property map. Please provide an array item type for property $prop.");
                    }

                    $arrayItemType = $arrayPropTypeMap[$prop];

                    if(self::isScalarType($arrayItemType)) {
                        $arrayItemSchema = JsonSchema::schemaFromScalarPhpType($arrayItemType, false);
                    } elseif ($arrayItemType === 'array') {
                        throw new \RuntimeException("Array item type of property $prop must not be 'array', only a scalar type or an existing class can be used as array item type.");
                    } else {
                        $arrayItemSchema = JsonSchema::typeRef(self::getTypeFromClass($arrayItemType));
                    }

                    $props[$prop] = JsonSchema::array($arrayItemSchema);
                } else {
                    $props[$prop] = JsonSchema::typeRef(self::getTypeFromClass($type));
                }

                if($isNullable) {
                    $props[$prop] = JsonSchema::nullOr($props[$prop]);
                }
            }

            self::$__schema = JsonSchema::object($props);
        }

        return self::$__schema;
    }

    private static function convertClassToTypeName(string $class): string
    {
        return substr(strrchr($class, '\\'), 1);
    }

    private static function getTypeFromClass(string $classOrType): string
    {
        if(!class_exists($classOrType)) {
            return $classOrType;
        }

        $refObj = new \ReflectionClass($classOrType);

        if($refObj->implementsInterface(ImmutableRecord::class)) {
            return call_user_func([$classOrType, 'type']);
        }

        return self::convertClassToTypeName($classOrType);
    }
}
