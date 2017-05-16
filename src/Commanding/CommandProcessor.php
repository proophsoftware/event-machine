<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Commanding;

use Fig\Http\Message\StatusCodeInterface;
use Prooph\Common\Messaging\Command;
use Prooph\EventMachine\Aggregate\ClosureAggregateTranslator;
use Prooph\EventSourcing\Aggregate\AggregateRepository;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventStore\EventStore;
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
     * @var SnapshotStore
     */
    private $snapshotStore;

    /**
     * @var AggregateRepository
     */
    private $aggregateRepository;

    public function __construct(
        string $commandName,
        string $aggregateType,
        bool $createAggregate,
        string $aggregateIdentifier,
        EventStore $eventStore,
        SnapshotStore $snapshotStore = null
    ) {
        $this->commandName = $commandName;
        $this->aggregateType = $aggregateType;
        $this->aggregateIdentifier = $aggregateIdentifier;
        $this->createAggregate = $createAggregate;
        $this->eventStore = $eventStore;
        $this->snapshotStore = $snapshotStore;
    }

    public function __invoke(GenericJsonSchemaCommand $command)
    {
        if($command->messageName() !== $this->commandName) {
            throw  new \RuntimeException('Wrong routing detected. Command processor is responsible for '
                . $this->commandName . ' but command '
                . $command->messageName() . ' received.', StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }


    }

    private function getAggregateRepository(): AggregateRepository
    {
        if(null === $this->aggregateRepository) {
            $this->aggregateRepository = new AggregateRepository(
                $this->eventStore,
                AggregateType::fromString($this->aggregateType),
                new ClosureAggregateTranslator(),
                $this->snapshotStore
            );
        }

        return $this->aggregateRepository;
    }
}
