<?php
declare(strict_types=1);

namespace Prooph\EventMachine\GraphQL;

use GraphQL\Language\AST\TypeDefinitionNode;

final class TypeConfigDecoratorProxy
{
    /**
     * @var TypeConfigDecorator
     */
    private $decorator;

    public function __construct(TypeConfigDecorator $decorator)
    {
        $this->decorator = $decorator;
    }

    public function __invoke(array $typeConfig, TypeDefinitionNode $node): array
    {
        return $this->decorator->decorate($typeConfig, $node);
    }
}
