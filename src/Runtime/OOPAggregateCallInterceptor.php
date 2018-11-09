<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Runtime;

use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Messaging\MessageBag;
use Prooph\EventMachine\Messaging\MessageFactory;
use Prooph\EventMachine\Messaging\MessageFactoryAware;
use Prooph\EventMachine\Runtime\OOP\AggregateAndEventBag;
use Prooph\EventMachine\Runtime\OOP\Port;
use Prooph\EventMachine\Util\DetermineVariableType;
use Prooph\EventMachine\Util\MapIterator;

final class OOPAggregateCallInterceptor implements CallInterceptor, MessageFactoryAware
{
    use DetermineVariableType;

    private const META_AGGREGATE_TYPE = '_aggregate_type';

    /**
     * @var Port
     */
    private $port;

    /**
     * @var CustomMessageCallInterceptor
     */
    private $customMessageInterceptor;

    public function __construct(Port $port, CustomMessageCallInterceptor $interceptor)
    {
        $this->port = $port;
        $this->customMessageInterceptor = $interceptor;
    }

    /**
     * @inheritdoc
     */
    public function callFirstAggregateFunction(string $aggregateType, callable $aggregateFunction, Message $command, $context = null): \Generator
    {
        if(!$command instanceof MessageBag) {
            throw new RuntimeException("Message passed to " . __METHOD__ . " should be of type " . MessageBag::class);
        }

        $aggregate = $this->port->callAggregateFactory($aggregateType, $aggregateFunction, $command->get(MessageBag::MESSAGE), $context);

        $events = $this->port->popRecordedEvents($aggregate);

        yield from new MapIterator(new \ArrayIterator($events), function ($event) use ($command, $aggregate, $aggregateType) {
            if(null === $event) return null;
            return $this->customMessageInterceptor->decorateEvent($event)
                ->withMessage(new AggregateAndEventBag($aggregate, $event))
                ->withAddedMetadata('_causation_id', $command->uuid()->toString())
                ->withAddedMetadata('_causation_name', $command->messageName());

        });
    }

    /**
     * @inheritdoc
     */
    public function callSubsequentAggregateFunction(string $aggregateType, callable $aggregateFunction, $aggregateState, Message $command, $context = null): \Generator
    {
        if(!$command instanceof MessageBag) {
            throw new RuntimeException("Message passed to " . __METHOD__ . " should be of type " . MessageBag::class);
        }

        $this->port->callAggregateWithCommand($aggregateState, $command->get(MessageBag::MESSAGE), $context);

        $events = $this->port->popRecordedEvents($aggregateState);

        yield from new MapIterator(new \ArrayIterator($events), function ($event) use ($command) {
            if(null === $event) return null;
            return $this->customMessageInterceptor->decorateEvent($event)
                ->withAddedMetadata('_causation_id', $command->uuid()->toString())
                ->withAddedMetadata('_causation_name', $command->messageName());
        });
    }

    /**
     * @inheritdoc
     */
    public function callApplyFirstEvent(callable $applyFunction, Message $event)
    {
        if(!$event instanceof MessageBag) {
            throw new RuntimeException("Message passed to " . __METHOD__ . " should be of type " . MessageBag::class);
        }

        $aggregateAndEventBag = $event->get(MessageBag::MESSAGE);

        if(!$aggregateAndEventBag instanceof AggregateAndEventBag) {
            throw new RuntimeException("MessageBag passed to " . __METHOD__ . " should contain a " . AggregateAndEventBag::class . " message.");
        }

        $aggregate = $aggregateAndEventBag->aggregate();
        $event = $aggregateAndEventBag->event();

        $this->port->applyEvent($aggregate, $event);

        return $aggregate;
    }


    public function callApplySubsequentEvent(callable $applyFunction, $aggregateState, Message $event)
    {
        if(!$event instanceof MessageBag) {
            throw new RuntimeException("Message passed to " . __METHOD__ . " should be of type " . MessageBag::class);
        }

        $this->port->applyEvent($aggregateState, $event->get(MessageBag::MESSAGE));

        return $aggregateState;
    }

    /**
     * @inheritdoc
     */
    public function callCommandPreProcessor($preProcessor, Message $command): Message
    {
        return $this->customMessageInterceptor->callCommandPreProcessor($preProcessor, $command);
    }

    /**
     * @inheritdoc
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, Message $command): string
    {
        return $this->customMessageInterceptor->getAggregateIdFromCommand($aggregateIdPayloadKey, $command);
    }

    /**
     * @inheritdoc
     */
    public function callContextProvider($contextProvider, Message $command)
    {
        return $this->customMessageInterceptor->callContextProvider($contextProvider, $command);
    }

    /**
     * @inheritdoc
     */
    public function prepareNetworkTransmission(Message $message): Message
    {
        if($message instanceof MessageBag) {
            $innerEvent = $message->getOrDefault(MessageBag::MESSAGE, new \stdClass());

            if($innerEvent instanceof AggregateAndEventBag) {
                $message = $message->withMessage($innerEvent->event());
            }
        }

        return $this->customMessageInterceptor->prepareNetworkTransmission($message);
    }

    /**
     * @inheritdoc
     */
    public function convertMessageReceivedFromNetwork(Message $message, $receivedFromEventStore = false): Message
    {
        $customMessageInBag = $this->customMessageInterceptor->convertMessageReceivedFromNetwork($message);

        if($receivedFromEventStore && $message->messageType() === Message::TYPE_EVENT) {
            $aggregateType = $message->metadata()[self::META_AGGREGATE_TYPE] ?? null;

            if(null === $aggregateType) {
                throw new RuntimeException("Event passed to " . __METHOD__ . " should have a metadata key: " . self::META_AGGREGATE_TYPE);
            }

            if(!$customMessageInBag instanceof MessageBag) {
                throw new RuntimeException("CustomMessageInterceptor is expected to return a " . MessageBag::class);
            }

            $aggregate = $this->port->reconstituteAggregate((string)$aggregateType, [$customMessageInBag->get(MessageBag::MESSAGE)]);

            $customMessageInBag = $customMessageInBag->withMessage(new AggregateAndEventBag($aggregate, $customMessageInBag->get(MessageBag::MESSAGE)));
        }

        return $customMessageInBag;
    }

    public function setMessageFactory(MessageFactory $messageFactory): void
    {
        $this->customMessageInterceptor->setMessageFactory($messageFactory);
    }
}
