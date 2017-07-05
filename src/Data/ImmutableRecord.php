<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Data;

interface ImmutableRecord
{
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
