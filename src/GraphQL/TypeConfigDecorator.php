<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
