<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\JsonSchema;


final class JsonSchema
{
    public static function object(array $requiredProps, array $optionalProps = [], $additionalProperties = false): array
    {
        return [
            'type' => 'object',
            'required' => array_keys($requiredProps),
            'additionalProperties' => $additionalProperties,
            'properties' => array_merge($requiredProps, $optionalProps)
        ];
    }

    private function __construct()
    {
        //static class only
    }
}
