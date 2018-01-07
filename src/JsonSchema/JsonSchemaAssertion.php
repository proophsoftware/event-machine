<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\JsonSchema;

interface JsonSchemaAssertion
{
    public function assert(string $objectName, array $data, array $jsonSchema);
}
