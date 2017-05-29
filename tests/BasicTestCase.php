<?php
declare(strict_types = 1);

namespace Prooph\EventMachineTest;

use PHPUnit\Framework\TestCase;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Aggregate\ClosureAggregateTranslator;
use Prooph\EventMachine\Aggregate\GenericAggregateRoot;
use Prooph\EventMachine\Commanding\GenericJsonSchemaCommand;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Prooph\EventMachine\JsonSchema\WebmozartJsonSchemaAssertion;
use Prophecy\Argument;

class BasicTestCase extends TestCase
{
    /**
     * @var JsonSchemaAssertion
     */
    private $jsonSchemaAssertion;

    /**
     * @var MessageFactory
     */
    private $commandMessageFactory;

    /**
     * @var MessageFactory
     */
    private $eventMessageFactory;

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

    protected function getMockedCommandMessageFactory(): MessageFactory
    {
        if(null === $this->commandMessageFactory) {
            $messageFactory = $this->prophesize(MessageFactory::class);

            $schemaAssertion = $this->prophesize(JsonSchemaAssertion::class);

            $schemaAssertion->assert(Argument::any(), Argument::any(), Argument::any())->will(function () {});

            $messageFactory->createMessageFromArray(Argument::any(), Argument::any())->will(function ($args) use($schemaAssertion) {
                list($commandName, $commandData) = $args;
                if(!isset($commandData['payload'])) {
                    $commandData = [
                        'payload' => $commandData
                    ];
                }
                $command = new GenericJsonSchemaCommand($commandName, $commandData['payload'], [], $schemaAssertion->reveal());

                return $command->withMetadata($commandData['metadata'] ?? []);
            });

            $this->commandMessageFactory = $messageFactory->reveal();
        }

        return $this->commandMessageFactory;
    }

    protected function getMockedEventMessageFactory(): MessageFactory
    {
        if(null === $this->eventMessageFactory) {
            $messageFactory = $this->prophesize(MessageFactory::class);

            $schemaAssertion = $this->prophesize(JsonSchemaAssertion::class);

            $schemaAssertion->assert(Argument::any(), Argument::any(), Argument::any())->will(function () {});

            $messageFactory->createMessageFromArray(Argument::any(), Argument::any())->will(function ($args) use($schemaAssertion) {
                list($eventName, $eventData) = $args;
                $event = new GenericJsonSchemaEvent($eventName, $eventData['payload'], [], $schemaAssertion->reveal());

                return $event->withMetadata($eventData['metadata'] ?? []);
            });

            $this->eventMessageFactory = $messageFactory->reveal();
        }

        return $this->eventMessageFactory;
    }
}
