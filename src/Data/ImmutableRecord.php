<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Data;

use Prooph\EventMachine\JsonSchema\Type;

interface ImmutableRecord
{
    const PHP_TYPE_STRING = 'string';
    const PHP_TYPE_INT = 'int';
    const PHP_TYPE_FLOAT = 'float';
    const PHP_TYPE_BOOL = 'bool';
    const PHP_TYPE_ARRAY = 'array';

    /**
     * Name of the immutable record type
     *
     * @return string
     */
    public static function __type(): string;

    /**
     * @return Type JSON Schema of the type
     */
    public static function __schema(): Type;

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
