<?php

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema\Type;

use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type;

class EnumType implements Type
{
    use NullableType;

    /**
     * @var string|array
     */
    private $type = JsonSchema::TYPE_STRING;

    /**
     * @var string[]
     */
    private $entries;

    public function __construct(string ...$entries)
    {
        $this->entries = $entries;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'enum' => $this->entries
        ];
    }
}
