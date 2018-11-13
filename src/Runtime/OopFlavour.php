<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Runtime;

use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Messaging\MessageBag;
use Prooph\EventMachine\Messaging\MessageFactory;
use Prooph\EventMachine\Messaging\MessageFactoryAware;
use Prooph\EventMachine\Runtime\Oop\AggregateAndEventBag;
use Prooph\EventMachine\Runtime\Oop\Port;
use Prooph\EventMachine\Util\DetermineVariableType;
use Prooph\EventMachine\Util\MapIterator;

/**
 * Class OopFlavour
 *
 * Event Sourcing can be implemented using either a functional programming approach (pure aggregate functions + immutable data types)
 * or an object-oriented approach with stateful aggregates. The latter is supported by the OopFlavour.
 *
 * Aggregates manage their state internally. Event Machine takes over the rest like history replays and event persistence.
 * You can focus on the business logic with a 100% decoupled domain model.
 *
 * Decoupling is achieved by implementing the Oop\Port tailored to your domain model.
 *
 * The OopFlavour uses a FunctionalFlavour internally. This is because the OopFlavour also requires type-safe messages.
 *
 *
 * @package Prooph\EventMachine\Runtime
 */
final class OopFlavour implements Flavour, MessageFactoryAware
{
    use DetermineVariableType;

    private const META_AGGREGATE_TYPE = '_aggregate_type';

    /**
     * @var Port
     */
    private $port;

    /**
     * @var FunctionalFlavour
     */
    private $functionalFlavour;

    public function __construct(Port $port, FunctionalFlavour $flavour)
    {
        $this->port = $port;
        $this->functionalFlavour = $flavour;
    }

    /**
     * {@inheritdoc}
     */
    public function callAggregateFactory(string $aggregateType, callable $aggregateFunction, Message $command, $context = null): \Generator
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $aggregate = $this->port->callAggregateFactory($aggregateType, $aggregateFunction, $command->get(MessageBag::MESSAGE), $context);

        $events = $this->port->popRecordedEvents($aggregate);

        yield from new MapIterator(new \ArrayIterator($events), function ($event) use ($command, $aggregate, $aggregateType) {
            if (null === $event) {
                return null;
            }

            return $this->functionalFlavour->decorateEvent($event)
                ->withMessage(new AggregateAndEventBag($aggregate, $event))
                ->withAddedMetadata('_causation_id', $command->uuid()->toString())
                ->withAddedMetadata('_causation_name', $command->messageName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callSubsequentAggregateFunction(string $aggregateType, callable $aggregateFunction, $aggregateState, Message $command, $context = null): \Generator
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $this->port->callAggregateWithCommand($aggregateState, $command->get(MessageBag::MESSAGE), $context);

        $events = $this->port->popRecordedEvents($aggregateState);

        yield from new MapIterator(new \ArrayIterator($events), function ($event) use ($command) {
            if (null === $event) {
                return null;
            }

            return $this->functionalFlavour->decorateEvent($event)
                ->withAddedMetadata('_causation_id', $command->uuid()->toString())
                ->withAddedMetadata('_causation_name', $command->messageName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callApplyFirstEvent(callable $applyFunction, Message $event)
    {
        if (! $event instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $aggregateAndEventBag = $event->get(MessageBag::MESSAGE);

        if (! $aggregateAndEventBag instanceof AggregateAndEventBag) {
            throw new RuntimeException('MessageBag passed to ' . __METHOD__ . ' should contain a ' . AggregateAndEventBag::class . ' message.');
        }

        $aggregate = $aggregateAndEventBag->aggregate();
        $event = $aggregateAndEventBag->event();

        $this->port->applyEvent($aggregate, $event);

        return $aggregate;
    }

    public function callApplySubsequentEvent(callable $applyFunction, $aggregateState, Message $event)
    {
        if (! $event instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $this->port->applyEvent($aggregateState, $event->get(MessageBag::MESSAGE));

        return $aggregateState;
    }

    /**
     * {@inheritdoc}
     */
    public function callCommandPreProcessor($preProcessor, Message $command): Message
    {
        return $this->functionalFlavour->callCommandPreProcessor($preProcessor, $command);
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, Message $command): string
    {
        return $this->functionalFlavour->getAggregateIdFromCommand($aggregateIdPayloadKey, $command);
    }

    /**
     * {@inheritdoc}
     */
    public function callContextProvider($contextProvider, Message $command)
    {
        return $this->functionalFlavour->callContextProvider($contextProvider, $command);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareNetworkTransmission(Message $message): Message
    {
        if ($message instanceof MessageBag) {
            $innerEvent = $message->getOrDefault(MessageBag::MESSAGE, new \stdClass());

            if ($innerEvent instanceof AggregateAndEventBag) {
                $message = $message->withMessage($innerEvent->event());
            }
        }

        return $this->functionalFlavour->prepareNetworkTransmission($message);
    }

    /**
     * {@inheritdoc}
     */
    public function convertMessageReceivedFromNetwork(Message $message, $receivedFromEventStore = false): Message
    {
        $customMessageInBag = $this->functionalFlavour->convertMessageReceivedFromNetwork($message);

        if ($receivedFromEventStore && $message->messageType() === Message::TYPE_EVENT) {
            $aggregateType = $message->metadata()[self::META_AGGREGATE_TYPE] ?? null;

            if (null === $aggregateType) {
                throw new RuntimeException('Event passed to ' . __METHOD__ . ' should have a metadata key: ' . self::META_AGGREGATE_TYPE);
            }

            if (! $customMessageInBag instanceof MessageBag) {
                throw new RuntimeException('CustomMessageInterceptor is expected to return a ' . MessageBag::class);
            }

            $aggregate = $this->port->reconstituteAggregate((string) $aggregateType, [$customMessageInBag->get(MessageBag::MESSAGE)]);

            $customMessageInBag = $customMessageInBag->withMessage(new AggregateAndEventBag($aggregate, $customMessageInBag->get(MessageBag::MESSAGE)));
        }

        return $customMessageInBag;
    }

    /**
     * {@inheritdoc}
     */
    public function callProjector($projector, string $appVersion, string $projectionName, Message $event): void
    {
        $this->functionalFlavour->callProjector($projector, $appVersion, $projectionName, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function convertAggregateStateToArray($aggregateState): array
    {
        return $this->port->serializeAggregate($aggregateState);
    }

    public function setMessageFactory(MessageFactory $messageFactory): void
    {
        $this->functionalFlavour->setMessageFactory($messageFactory);
    }

    public function callEventListener(callable $listener, Message $event): void
    {
        $this->functionalFlavour->callEventListener($listener, $event);
    }
}
