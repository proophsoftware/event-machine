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

use Prooph\EventMachine\Data\DataConverter;
use Prooph\EventMachine\Data\ImmutableRecordDataConverter;
use Prooph\EventMachine\Exception\NoGeneratorException;
use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Messaging\MessageBag;
use Prooph\EventMachine\Messaging\MessageFactory;
use Prooph\EventMachine\Messaging\MessageFactoryAware;
use Prooph\EventMachine\Projecting\AggregateProjector;
use Prooph\EventMachine\Projecting\CustomEventProjector;
use Prooph\EventMachine\Querying\SyncResolver;
use Prooph\EventMachine\Runtime\Functional\Port;
use Prooph\EventMachine\Util\MapIterator;
use React\Promise\Deferred;

/**
 * Class FunctionalFlavour
 *
 * Similar to the PrototypingFlavour pure aggregate functions + immutable data types are used.
 * Once you leave the prototyping or experimentation phase of a project behind, you'll likely want to harden the domain model.
 * This includes dedicated command, event and query types. If you find yourself in this situation the FunctionalFlavour
 * is for you. All parts of the system that handle messages will receive your own message types when using the
 * FunctionalFlavour.
 *
 * Implement a Functional\Port to map between Event Machine's generic messages and your type-safe counterparts.
 *
 * @package Prooph\EventMachine\Runtime
 */
final class FunctionalFlavour implements Flavour, MessageFactoryAware
{
    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var Port
     */
    private $port;

    /**
     * @var DataConverter
     */
    private $dataConverter;

    public function __construct(Port $port, DataConverter $dataConverter = null)
    {
        $this->port = $port;

        if (null === $dataConverter) {
            $dataConverter = new ImmutableRecordDataConverter();
        }

        $this->dataConverter = $dataConverter;
    }

    public function setMessageFactory(MessageFactory $messageFactory): void
    {
        $this->messageFactory = $messageFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function callCommandPreProcessor($preProcessor, Message $command): Message
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        return $command->withMessage($this->port->callCommandPreProcessor($command->get(MessageBag::MESSAGE), $preProcessor));
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, Message $command): string
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        return $this->port->getAggregateIdFromCommand($aggregateIdPayloadKey, $command->get(MessageBag::MESSAGE));
    }

    /**
     * {@inheritdoc}
     */
    public function callContextProvider($contextProvider, Message $command)
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        return $this->port->callContextProvider($command->get(MessageBag::MESSAGE), $contextProvider);
    }

    /**
     * {@inheritdoc}
     */
    public function callAggregateFactory(string $aggregateType, callable $aggregateFunction, Message $command, $context = null): \Generator
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $events = $aggregateFunction($command->get(MessageBag::MESSAGE), $context);

        if (! $events instanceof \Generator) {
            throw NoGeneratorException::forAggregateTypeAndCommand($aggregateType, $command);
        }

        yield from new MapIterator($events, function ($event) use ($command) {
            if (null === $event) {
                return null;
            }

            return $this->port->decorateEvent($event)
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

        $events = $aggregateFunction($aggregateState, $command->get(MessageBag::MESSAGE), $context);

        if (! $events instanceof \Generator) {
            throw NoGeneratorException::forAggregateTypeAndCommand($aggregateType, $command);
        }

        yield from new MapIterator($events, function ($event) use ($command) {
            if (null === $event) {
                return null;
            }

            return $this->port->decorateEvent($event)
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

        return $applyFunction($event->get(MessageBag::MESSAGE));
    }

    /**
     * {@inheritdoc}
     */
    public function callApplySubsequentEvent(callable $applyFunction, $aggregateState, Message $event)
    {
        if (! $event instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        return $applyFunction($aggregateState, $event->get(MessageBag::MESSAGE));
    }

    /**
     * {@inheritdoc}
     */
    public function prepareNetworkTransmission(Message $message): Message
    {
        if ($message instanceof MessageBag && $message->hasMessage()) {
            $payload = $this->port->serializePayload($message->get(MessageBag::MESSAGE));

            return $this->messageFactory->setPayloadFor($message, $payload);
        }

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function convertMessageReceivedFromNetwork(Message $message, $firstAggregateEvent = false): Message
    {
        if ($message instanceof MessageBag && $message->hasMessage()) {
            //Message is already decorated
            return $message;
        }

        return new MessageBag(
            $message->messageName(),
            $message->messageType(),
            $this->port->deserialize($message),
            $message->metadata(),
            $message->uuid(),
            $message->createdAt()
        );
    }

    public function decorateEvent($customEvent): MessageBag
    {
        return $this->port->decorateEvent($customEvent);
    }

    /**
     * {@inheritdoc}
     */
    public function callProjector($projector, string $appVersion, string $projectionName, Message $event): void
    {
        if ($projector instanceof AggregateProjector) {
            $projector->handle($appVersion, $projectionName, $event);

            return;
        }

        if (! $projector instanceof CustomEventProjector) {
            throw new RuntimeException(__METHOD__ . ' can only call instances of ' . CustomEventProjector::class);
        }

        if (! $event instanceof MessageBag) {
            //Normalize event if possible
            if ($event instanceof Message) {
                $event = $this->port->decorateEvent($this->port->deserialize($event));
            } else {
                throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
            }
        }

        //Normalize MessageBag if possible
        //MessageBag can contain payload instead of custom event, if projection is called with in-memory recorded event
        if (! $event->hasMessage()) {
            $event = $this->port->decorateEvent($this->port->deserialize($event));
        }

        $projector->handle($appVersion, $projectionName, $event->get(MessageBag::MESSAGE));
    }

    /**
     * @param mixed $aggregateState
     * @return array
     */
    public function convertAggregateStateToArray($aggregateState): array
    {
        return $this->dataConverter->convertDataToArray($aggregateState);
    }

    public function callEventListener(callable $listener, Message $event): void
    {
        if (! $event instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        //Normalize MessageBag if possible
        ////MessageBag can contain payload instead of custom event, if listener is called with in-memory recorded event
        if (! $event->hasMessage()) {
            $event = $this->port->decorateEvent($this->port->deserialize($event));
        }

        $listener($event->get(MessageBag::MESSAGE));
    }

    public function callQueryResolver(callable $resolver, Message $query, Deferred $deferred): void
    {
        if (! $query instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $query = $query->get(MessageBag::MESSAGE);

        if (\is_object($resolver) && $resolver instanceof SyncResolver) {
            try {
                $result = $resolver($query);
            } catch (\Throwable $err) {
                $deferred->reject($err);
            }

            $deferred->resolve($result);

            return;
        }

        $resolver($query, $deferred);
    }
}
