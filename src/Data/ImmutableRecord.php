<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Data;

interface ImmutableRecord
{
    /**
     * Name of the immutable record type
     *
     * @return string
     */
    public static function type(): string;

    /**
     * @return array JSON Schema of the type
     */
    public static function schema(): array;

    /**
     * @param array $recordData
     * @return static
     */
    public static function fromRecordData(array $recordData);

    /**
     * @param array $nativeData
     * @return static
     */
    public static function fromArray(array $nativeData);

    /**
     * @param array $recordData
     * @return static
     */
    public function with(array $recordData);

    public function toArray(): array;
}
