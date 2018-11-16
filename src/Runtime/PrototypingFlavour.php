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

use Prooph\EventMachine\Aggregate\ContextProvider;
use Prooph\EventMachine\Commanding\CommandPreProcessor;
use Prooph\EventMachine\Data\DataConverter;
use Prooph\EventMachine\Data\ImmutableRecordDataConverter;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\Exception\InvalidEventFormatException;
use Prooph\EventMachine\Exception\MissingAggregateIdentifierException;
use Prooph\EventMachine\Exception\NoGeneratorException;
use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Messaging\MessageFactory;
use Prooph\EventMachine\Messaging\MessageFactoryAware;
use Prooph\EventMachine\Projecting\AggregateProjector;
use Prooph\EventMachine\Projecting\Projector;
use Prooph\EventMachine\Querying\SyncResolver;
use Prooph\EventMachine\Util\DetermineVariableType;
use Prooph\EventMachine\Util\MapIterator;
use React\Promise\Deferred;

/**
 * Class PrototypingFlavour
 *
 * Default Flavour used by Event Machine if no other Flavour is configured.
 *
 * This Flavour is tailored to rapid prototyping of event sourced domain models. Event Machine passes
 * generic messages directly into pure aggregate functions, command preprocessors, context providers and so on.
 *
 * Aggregate functions can use a short array syntax to describe events that should be recorded by Event Machine.
 * Check the tutorial at: https://proophsoftware.github.io/event-machine/tutorial/
 * It uses the PrototypingFlavour.
 *
 * @package Prooph\EventMachine\Runtime
 */
final class PrototypingFlavour implements Flavour, MessageFactoryAware
{
    use DetermineVariableType;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var DataConverter
     */
    private $stateConverter;

    public function __construct(DataConverter $dataConverter = null)
    {
        if (null === $dataConverter) {
            $dataConverter = new ImmutableRecordDataConverter();
        }

        $this->stateConverter = $dataConverter;
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
        if (! $preProcessor instanceof CommandPreProcessor) {
            throw new RuntimeException(
                'By default a CommandPreProcessor should implement the interface: '
                . CommandPreProcessor::class . '. Got ' . self::getType($preProcessor)
            );
        }

        $command = $preProcessor->preProcess($command);

        //@TODO: Remove check after fixing CommandPreProcessor interface in v2.0
        if (! $command instanceof Message) {
            //Turn prooph message into Event Machine message (which extends prooph message in v1.0)
            $command = $this->messageFactory->createMessageFromArray(
                $command->messageName(),
                [
                    'uuid' => $command->uuid(),
                    'created_at' => $command->createdAt(),
                    'payload' => $command->payload(),
                    'metadata' => $command->metadata(),
                ]
            );
        }

        return $command;
    }

    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, Message $command): string
    {
        $payload = $command->payload();

        if (! \array_key_exists($aggregateIdPayloadKey, $payload)) {
            throw MissingAggregateIdentifierException::inCommand($command, $aggregateIdPayloadKey);
        }

        return (string) $payload[$aggregateIdPayloadKey];
    }

    /**
     * {@inheritdoc}
     */
    public function callContextProvider($contextProvider, Message $command)
    {
        if (! $contextProvider instanceof ContextProvider) {
            throw new RuntimeException(
                'By default a ContextProvider should implement the interface: '
                . ContextProvider::class . '. Got ' . self::getType($contextProvider)
            );
        }

        return $contextProvider->provide($command);
    }

    /**
     * {@inheritdoc}
     */
    public function callAggregateFactory(string $aggregateType, callable $aggregateFunction, Message $command, $context = null): \Generator
    {
        $events = $aggregateFunction($command, $context);

        if (! $events instanceof \Generator) {
            throw NoGeneratorException::forAggregateTypeAndCommand($aggregateType, $command);
        }

        yield from new MapIterator($events, function ($event) use ($aggregateType, $command) {
            if (null === $event) {
                return null;
            }

            return $this->mapToMessage($event, $aggregateType, $command);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callSubsequentAggregateFunction(string $aggregateType, callable $aggregateFunction, $aggregateState, Message $command, $context = null): \Generator
    {
        $events = $aggregateFunction($aggregateState, $command, $context);

        if (! $events instanceof \Generator) {
            throw NoGeneratorException::forAggregateTypeAndCommand($aggregateType, $command);
        }

        yield from new MapIterator($events, function ($event) use ($aggregateType, $command) {
            if (null === $event) {
                return null;
            }

            return $this->mapToMessage($event, $aggregateType, $command);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callApplyFirstEvent(callable $applyFunction, Message $event)
    {
        return $applyFunction($event);
    }

    /**
     * {@inheritdoc}
     */
    public function callApplySubsequentEvent(callable $applyFunction, $aggregateState, Message $event)
    {
        return $applyFunction($aggregateState, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareNetworkTransmission(Message $message): Message
    {
        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function convertMessageReceivedFromNetwork(Message $message, $firstAggregateEvent = false): Message
    {
        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function callProjector($projector, string $appVersion, string $projectionName, Message $event): void
    {
        if (! $projector instanceof Projector && ! $projector instanceof AggregateProjector) {
            throw new RuntimeException(__METHOD__ . ' can only call instances of ' . Projector::class);
        }

        $projector->handle($appVersion, $projectionName, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function convertAggregateStateToArray($aggregateState): array
    {
        return $this->stateConverter->convertDataToArray($aggregateState);
    }

    public function callEventListener(callable $listener, Message $event): void
    {
        $listener($event);
    }

    public function callQueryResolver(callable $resolver, Message $query, Deferred $deferred): void
    {
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

    private function mapToMessage($event, string $aggregateType, Message $command): Message
    {
        if (! \is_array($event) || ! \array_key_exists(0, $event) || ! \array_key_exists(1, $event)
            || ! \is_string($event[0]) || ! \is_array($event[1])) {
            throw InvalidEventFormatException::invalidEvent($aggregateType, $command);
        }
        [$eventName, $payload] = $event;

        $metadata = [];

        if (\array_key_exists(2, $event)) {
            $metadata = $event[2];
            if (! \is_array($metadata)) {
                throw InvalidEventFormatException::invalidMetadata($metadata, $aggregateType, $command);
            }
        }

        /** @var GenericJsonSchemaEvent $event */
        $event = $this->messageFactory->createMessageFromArray($eventName, [
            'payload' => $payload,
            'metadata' => \array_merge([
                '_causation_id' => $command->uuid()->toString(),
                '_causation_name' => $command->messageName(),
            ], $metadata),
        ]);

        return $event;
    }
}
