<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Commanding;

use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Aggregate\ClosureAggregateTranslator;
use Prooph\EventMachine\Aggregate\ContextProvider;
use Prooph\EventMachine\Aggregate\Exception\AggregateNotFound;
use Prooph\EventMachine\Aggregate\GenericAggregateRoot;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Runtime\CallInterceptor;
use Prooph\EventSourcing\Aggregate\AggregateRepository;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\StreamName;
use Prooph\SnapshotStore\SnapshotStore;

final class CommandProcessor
{
    /**
     * @var string
     */
    private $commandName;

    /**
     * @var string
     */
    private $aggregateType;

    /**
     * @var string
     */
    private $aggregateIdentifier;

    /**
     * @var bool
     */
    private $createAggregate;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var array
     */
    private $eventRecorderMap;

    /**
     * @var array
     */
    private $eventApplyMap;

    /**
     * @var string
     */
    private $streamName;

    /**
     * @var callable
     */
    private $aggregateFunction;

    /**
     * @var CallInterceptor
     */
    private $callInterceptor;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var SnapshotStore
     */
    private $snapshotStore;

    /**
     * @var ContextProvider|null
     */
    private $contextProvider;

    /**
     * @var AggregateRepository
     */
    private $aggregateRepository;

    public static function fromDescriptionArrayAndDependencies(
        array $description,
        CallInterceptor $callInterceptor,
        MessageFactory $messageFactory,
        EventStore $eventStore,
        SnapshotStore $snapshotStore = null,
        ContextProvider $contextProvider = null
    ): self {
        if (! array_key_exists('commandName', $description)) {
            throw new \InvalidArgumentException('Missing key commandName in commandProcessorDescription');
        }

        if (! array_key_exists('createAggregate', $description)) {
            throw new \InvalidArgumentException('Missing key createAggregate in commandProcessorDescription');
        }

        if (! array_key_exists('aggregateType', $description)) {
            throw new \InvalidArgumentException('Missing key aggregateType in commandProcessorDescription');
        }

        if (! array_key_exists('aggregateIdentifier', $description)) {
            throw new \InvalidArgumentException('Missing key aggregateIdentifier in commandProcessorDescription');
        }

        if (! array_key_exists('aggregateFunction', $description)) {
            throw new \InvalidArgumentException('Missing key aggregateFunction in commandProcessorDescription');
        }

        if (! array_key_exists('eventRecorderMap', $description)) {
            throw new \InvalidArgumentException('Missing key eventRecorderMap in commandProcessorDescription');
        }

        if (! array_key_exists('eventApplyMap', $description)) {
            throw new \InvalidArgumentException('Missing key eventApplyMap in commandProcessorDescription');
        }

        if (! array_key_exists('streamName', $description)) {
            throw new \InvalidArgumentException('Missing key streamName in commandProcessorDescription');
        }

        return new self(
            $description['commandName'],
            $description['aggregateType'],
            $description['createAggregate'],
            $description['aggregateIdentifier'],
            $description['aggregateFunction'],
            $description['eventRecorderMap'],
            $description['eventApplyMap'],
            $description['streamName'],
            $callInterceptor,
            $messageFactory,
            $eventStore,
            $snapshotStore,
            $contextProvider
        );
    }

    public function __construct(
        string $commandName,
        string $aggregateType,
        bool $createAggregate,
        string $aggregateIdentifier,
        callable $aggregateFunction,
        array $eventRecorderMap,
        array $eventApplyMap,
        string $streamName,
        CallInterceptor $callInterceptor,
        MessageFactory $messageFactory,
        EventStore $eventStore,
        SnapshotStore $snapshotStore = null,
        ContextProvider $contextProvider = null
    ) {
        $this->commandName = $commandName;
        $this->aggregateType = $aggregateType;
        $this->aggregateIdentifier = $aggregateIdentifier;
        $this->createAggregate = $createAggregate;
        $this->aggregateFunction = $aggregateFunction;
        $this->eventRecorderMap = $eventRecorderMap;
        $this->eventApplyMap = $eventApplyMap;
        $this->streamName = $streamName;
        $this->callInterceptor = $callInterceptor;
        $this->messageFactory = $messageFactory;
        $this->eventStore = $eventStore;
        $this->snapshotStore = $snapshotStore;
        $this->contextProvider = $contextProvider;
    }

    public function __invoke(Message $command)
    {
        if ($command->messageName() !== $this->commandName) {
            throw  new \RuntimeException('Wrong routing detected. Command processor is responsible for '
                . $this->commandName . ' but command '
                . $command->messageName() . ' received.');
        }

        $arId = $this->callInterceptor->getAggregateIdFromCommand($this->aggregateIdentifier, $command);
        $arRepository = $this->getAggregateRepository($arId);

        $aggregate = null;
        $aggregateState = null;
        $context = null;

        if ($this->createAggregate) {
            $aggregate = new GenericAggregateRoot($arId, AggregateType::fromString($this->aggregateType), $this->eventApplyMap, $this->callInterceptor);
        } else {
            /** @var GenericAggregateRoot $aggregate */
            $aggregate = $arRepository->getAggregateRoot($arId);

            if (! $aggregate) {
                throw AggregateNotFound::with($this->aggregateType, $arId);
            }

            $aggregateState = $aggregate->currentState();
        }

        if ($this->contextProvider) {
            $context = $this->callInterceptor->callContextProvider($this->contextProvider, $command);
        }

        $arFunc = $this->aggregateFunction;

        if($this->createAggregate) {
            $events = $this->callInterceptor->callFirstAggregateFunction($this->aggregateType, $arFunc, $command, $context);
        } else {
            $events = $this->callInterceptor->callSubsequentAggregateFunction($this->aggregateType, $arFunc, $aggregateState, $command, $context);
        }

        foreach ($events as $event) {
            if (! $event) {
                continue;
            }
            $aggregate->recordThat($event);
        }

        $arRepository->saveAggregateRoot($aggregate);
    }

    private function getAggregateRepository(string $aggregateId): AggregateRepository
    {
        if (null === $this->aggregateRepository) {
            $this->aggregateRepository = new AggregateRepository(
                $this->eventStore,
                AggregateType::fromString($this->aggregateType),
                new ClosureAggregateTranslator($aggregateId, $this->eventApplyMap, $this->callInterceptor),
                $this->snapshotStore,
                new StreamName($this->streamName)
            );
        }

        return $this->aggregateRepository;
    }
}
