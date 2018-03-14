<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Querying;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type;

final class QueryDescription
{
    /**
     * @var EventMachine
     */
    private $eventMachine;

    /**
     * @var string
     */
    private $queryName;

    /**
     * @var string|callable
     */
    private $resolver;

    /**
     * @var array
     */
    private $returnType;

    public function __construct(string $queryName, EventMachine $eventMachine)
    {
        $this->eventMachine = $eventMachine;
        $this->queryName = $queryName;
    }

    public function __invoke(): array
    {
        $this->assertResolverAndReturnTypeAreSet();

        return [
            'name' => $this->queryName,
            'resolver' => $this->resolver,
            'returnType' => $this->returnType,
        ];
    }

    public function resolveWith($resolver): self
    {
        if (! is_string($resolver) && ! is_callable($resolver)) {
            throw new \InvalidArgumentException('Resolver should be either a service id string or a callable function. Got '
                . (is_object($resolver) ? get_class($resolver) : gettype($resolver)));
        }

        $this->resolver = $resolver;

        return $this;
    }

    public function setReturnType(Type $typeSchema): self
    {
        $typeSchema = $typeSchema->toArray();
        $this->eventMachine->jsonSchemaAssertion()->assert("Query return type {$this->queryName}", $typeSchema, JsonSchema::metaSchema());

        $this->returnType = $typeSchema;

        return $this;
    }

    public function returnType(): ?array
    {
        return $this->returnType;
    }

    private function assertResolverAndReturnTypeAreSet(): void
    {
        if (! $this->resolver) {
            throw new \RuntimeException("Missing resolver for query {$this->queryName}");
        }

        if (! $this->returnType) {
            throw new \RuntimeException("Missing return type for query {$this->queryName}");
        }
    }
}
