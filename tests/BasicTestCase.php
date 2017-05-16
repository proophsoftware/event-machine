<?php
declare(strict_types = 1);

namespace Prooph\EventMachineTest;

use PHPUnit\Framework\TestCase;
use Prooph\EventMachine\Aggregate\ClosureAggregateTranslator;
use Prooph\EventMachine\Aggregate\GenericAggregateRoot;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Prooph\EventMachine\JsonSchema\WebmozartJsonSchemaAssertion;

class BasicTestCase extends TestCase
{
    /**
     * @var JsonSchemaAssertion
     */
    private $jsonSchemaAssertion;

    /**
     * @param GenericAggregateRoot $aggregateRoot
     * @return GenericJsonSchemaEvent[]
     */
    protected function extractRecordedEvents(GenericAggregateRoot $aggregateRoot): array
    {
        $aggregateRootTranslator = new ClosureAggregateTranslator('unknown', []);

        return $aggregateRootTranslator->extractPendingStreamEvents($aggregateRoot);
    }

    protected function getJsonSchemaAssertion(): JsonSchemaAssertion
    {
        if(null === $this->jsonSchemaAssertion) {
            $this->jsonSchemaAssertion = new WebmozartJsonSchemaAssertion();
        }

        return $this->jsonSchemaAssertion;
    }
}
