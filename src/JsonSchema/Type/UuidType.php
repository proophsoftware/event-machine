<?php

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema\Type;

use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type;
use Ramsey\Uuid\Uuid;

class UuidType implements Type
{
    use NullableType;

    private $type = JsonSchema::TYPE_STRING;

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'pattern' => Uuid::VALID_PATTERN
        ];
    }
}
