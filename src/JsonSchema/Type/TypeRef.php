<?php

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema\Type;

use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type;

class TypeRef implements Type
{
    /**
     * @var string
     */
    private $referencedTypeName;

    private $nullable = false;

    public function __construct(string $referencedTypeName)
    {
        $this->referencedTypeName = $referencedTypeName;
    }

    public function referencedTypeName(): string
    {
        return $this->referencedTypeName;
    }

    public function toArray(): array
    {
        $refArr = ['$ref' => '#/'.JsonSchema::DEFINITIONS.'/'.$this->referencedTypeName,];

        if($this->nullable) {
            return [
                'oneOf' => [
                    ['type' => JsonSchema::TYPE_NULL],
                    $refArr
                ]
            ];
        }

        return $refArr;
    }

    public function asNullable(): Type
    {
        $cp = clone $this;
        $cp->nullable = true;
        return $cp;
    }
}
