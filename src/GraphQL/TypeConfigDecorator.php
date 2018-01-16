<?php
declare(strict_types=1);

namespace Prooph\EventMachine\GraphQL;

use GraphQL\Language\AST\TypeDefinitionNode;

interface TypeConfigDecorator
{
    /**
     * @param array $typeConfig
     * @param TypeDefinitionNode $node
     * @return array Modified type config
     */
    public function decorate(array $typeConfig, TypeDefinitionNode $node): array;
}
