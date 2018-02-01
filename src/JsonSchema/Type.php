<?php

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema;

interface Type
{
    public function toArray(): array;

    public function asNullable(): self;
}
