<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\JsonSchema;

interface JsonSchemaAssertion
{
    public function assert(array $data, array $jsonSchema);
}
