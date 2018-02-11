<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema\Type;

use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type;

final class ArrayType implements Type
{
    use NullableType;

    /**
     * @var string|array
     */
    private $type = JsonSchema::TYPE_ARRAY;

    /**
     * @var Type
     */
    private $itemSchema;

    /**
     * @var null|array
     */
    private $validation;

    public function __construct(Type $itemSchema, array $validation = null)
    {
        $this->itemSchema = $itemSchema;
        $this->validation = $validation;
    }

    public function toArray(): array
    {
        return array_merge([
            'type' => $this->type,
            'items' => $this->itemSchema->toArray(),
        ], (array) $this->validation);
    }
}
