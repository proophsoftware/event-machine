<?php

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema\Type;

use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type;

final class BoolType implements Type
{
    use NullableType;

    /**
     * @var string|array
     */
    private $type = JsonSchema::TYPE_BOOL;

    public function toArray(): array
    {
        return ['type' => $this->type];
    }
}
