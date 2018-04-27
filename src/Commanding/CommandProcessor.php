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
use Prooph\EventSourcing\Aggregate\AggregateRepository;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\StreamName;
use Prooph\SnapshotStore\SnapshotStore;
use Psr\Container\ContainerInterface;

final class CommandProcessor
{
    /**
     * @var ContainerInterface
     */
    private $container;

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
     * @var array
     */
    private $servicesToInject;

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
        ContainerInterface $container,
        array $description,
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

        if (! array_key_exists('servicesToInject', $description)) {
            throw new \InvalidArgumentException('Missing key servicesToInject in commandProcessorDescription');
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
            $container,
            $description['commandName'],
            $description['aggregateType'],
            $description['createAggregate'],
            $description['aggregateIdentifier'],
            $description['aggregateFunction'],
            $description['servicesToInject'],
            $description['eventRecorderMap'],
            $description['eventApplyMap'],
            $description['streamName'],
            $messageFactory,
            $eventStore,
            $snapshotStore,
            $contextProvider
        );
    }

    public function __construct(
        ContainerInterface $container,
        string $commandName,
        string $aggregateType,
        bool $createAggregate,
        string $aggregateIdentifier,
        callable $aggregateFunction,
        array $servicesToInject,
        array $eventRecorderMap,
        array $eventApplyMap,
        string $streamName,
        MessageFactory $messageFactory,
        EventStore $eventStore,
        SnapshotStore $snapshotStore = null,
        ContextProvider $contextProvider = null
    ) {
        $this->container = $container;
        $this->commandName = $commandName;
        $this->aggregateType = $aggregateType;
        $this->aggregateIdentifier = $aggregateIdentifier;
        $this->createAggregate = $createAggregate;
        $this->aggregateFunction = $aggregateFunction;
        $this->servicesToInject = $servicesToInject;
        $this->eventRecorderMap = $eventRecorderMap;
        $this->eventApplyMap = $eventApplyMap;
        $this->streamName = $streamName;
        $this->messageFactory = $messageFactory;
        $this->eventStore = $eventStore;
        $this->snapshotStore = $snapshotStore;
        $this->contextProvider = $contextProvider;
    }

    public function __invoke(GenericJsonSchemaCommand $command)
    {
        if ($command->messageName() !== $this->commandName) {
            throw  new \RuntimeException('Wrong routing detected. Command processor is responsible for '
                . $this->commandName . ' but command '
                . $command->messageName() . ' received.');
        }

        $payload = $command->payload();

        if (! array_key_exists($this->aggregateIdentifier, $payload)) {
            throw new \RuntimeException(sprintf(
                'Missing aggregate identifier %s in payload of command %s',
                $this->aggregateIdentifier,
                $this->commandName
            ));
        }

        $arId = (string) $payload[$this->aggregateIdentifier];
        $arRepository = $this->getAggregateRepository($arId);
        $arFuncArgs = [];

        if ($this->createAggregate) {
            $aggregate = new GenericAggregateRoot($arId, AggregateType::fromString($this->aggregateType), $this->eventApplyMap);
            $arFuncArgs[] = $command;
        } else {
            /** @var GenericAggregateRoot $aggregate */
            $aggregate = $arRepository->getAggregateRoot($arId);

            if (! $aggregate) {
                throw AggregateNotFound::with($this->aggregateType, $arId);
            }

            $arFuncArgs[] = $aggregate->currentState();
            $arFuncArgs[] = $command;
        }

        if ($this->contextProvider) {
            $arFuncArgs[] = $this->contextProvider->provide($command);
        }

        $arFunc = $this->aggregateFunction;

        $events = $arFunc(...$arFuncArgs, ...array_map(
            function ($service) {
                return $this->container->get($service);
            },
            $this->servicesToInject
        ));

        if (! $events instanceof \Generator) {
            throw new \InvalidArgumentException(
                'Expected aggregateFunction to be of type Generator. ' .
                'Did you forget the yield keyword in your command handler?'
            );
        }

        foreach ($events as $event) {
            if (! $event) {
                continue;
            }

            if (! is_array($event) || ! array_key_exists(0, $event) || ! array_key_exists(1, $event)
                || ! is_string($event[0]) || ! is_array($event[1])) {
                throw new \RuntimeException(sprintf(
                    'Event returned by aggregate of type %s while handling command %s does not has the format [string eventName, array payload]!',
                    $this->aggregateType,
                    $this->commandName
                ));
            }
            [$eventName, $payload] = $event;

            /** @var GenericJsonSchemaEvent $event */
            $event = $this->messageFactory->createMessageFromArray($eventName, [
                'payload' => $payload,
                'metadata' => [
                    '_causation_id' => $command->uuid()->toString(),
                    '_causation_name' => $this->commandName,
                ],
            ]);

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
                new ClosureAggregateTranslator($aggregateId, $this->eventApplyMap),
                $this->snapshotStore,
                new StreamName($this->streamName)
            );
        }

        return $this->aggregateRepository;
    }
}
