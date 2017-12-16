<?php

declare(strict_types = 1);

namespace Prooph\EventMachine\Data;


trait ImmutableRecordLogic
{
    private $propTypeMap = [];

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
        $this->buildPropTypeMap();

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

        foreach ($this->propTypeMap as $key => $type) {
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
            if(!isset($this->propTypeMap[$key])) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid property passed to Record %s. Got property with key ' . $key,
                    get_called_class()
                ));
            }

            $type = $this->propTypeMap[$key];

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
        foreach (array_keys($this->propTypeMap) as $key) {
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
        if(!isset($this->propTypeMap[$key])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid property passed to Record %s. Got property with key ' . $key,
                __CLASS__
            ));
        }

        $type = $this->propTypeMap[$key];

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

    private function buildPropTypeMap()
    {
        $refObj = new \ReflectionClass($this);

        $props = $refObj->getProperties();

        foreach ($props as $prop) {
            if($prop->getName() === 'propTypeMap') {
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

            $this->propTypeMap[$prop->getName()] = (string)$method->getReturnType();
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
}
