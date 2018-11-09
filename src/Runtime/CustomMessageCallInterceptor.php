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

use Prooph\EventMachine\Exception\NoGeneratorException;
use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Messaging\MessageBag;
use Prooph\EventMachine\Messaging\MessageFactory;
use Prooph\EventMachine\Messaging\MessageFactoryAware;
use Prooph\EventMachine\Runtime\CustomMessage\Port;
use Prooph\EventMachine\Util\MapIterator;

final class CustomMessageCallInterceptor implements CallInterceptor, MessageFactoryAware
{
    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var Port
     */
    private $port;

    public function __construct(Port $port)
    {
        $this->port = $port;
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

        return $command->withMessage($this->port->callCustomCommandPreProcessor($command->get(MessageBag::MESSAGE), $preProcessor));
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, Message $command): string
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        return $this->port->getAggregateIdFromCustomCommand($aggregateIdPayloadKey, $command->get(MessageBag::MESSAGE));
    }

    /**
     * {@inheritdoc}
     */
    public function callContextProvider($contextProvider, Message $command)
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        return $this->port->callCustomContextProvider($command->get(MessageBag::MESSAGE), $contextProvider);
    }

    /**
     * {@inheritdoc}
     */
    public function callFirstAggregateFunction(string $aggregateType, callable $aggregateFunction, Message $command, $context = null): \Generator
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
    public function convertMessageReceivedFromNetwork(Message $message, $receivedFromEventStore = false): Message
    {
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
}
