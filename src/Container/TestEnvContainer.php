<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Container;

use Prooph\Common\Event\ProophActionEventEmitter;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Persistence\DocumentStore\InMemoryDocumentStore;
use Prooph\EventStore\ActionEventEmitterEventStore;
use Prooph\EventStore\InMemoryEventStore;
use Prooph\EventStore\Projection\InMemoryProjectionManager;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\QueryBus;
use Prooph\SnapshotStore\Snapshot;
use Prooph\SnapshotStore\SnapshotStore;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class TestEnvContainer implements ContainerInterface
{
    private $commandBus;

    private $eventBus;

    private $queryBus;

    private $snapshotStore;

    private $eventStore;

    private $projectionManager;

    private $documentStore;

    /**
     * @var array
     */
    private $services;

    public function __construct(array $services = [])
    {
        $this->services = $services;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get($id)
    {
        switch ($id) {
            case EventMachine::SERVICE_ID_EVENT_STORE:
                if(null === $this->eventStore) {
                    $es = new InMemoryEventStore();
                    $es->create(new Stream(new StreamName('event_stream'), new \ArrayIterator([])));
                    $this->eventStore = new ActionEventEmitterEventStore($es, new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS));
                }
                return $this->eventStore;
            case EventMachine::SERVICE_ID_COMMAND_BUS:
                if(null === $this->commandBus) {
                    $this->commandBus = new CommandBus();
                }
                return $this->commandBus;
            case EventMachine::SERVICE_ID_EVENT_BUS:
                if(null === $this->eventBus) {
                    $this->eventBus = new EventBus();
                }
                return $this->eventBus;
            case EventMachine::SERVICE_ID_QUERY_BUS:
                if (null === $this->queryBus) {
                    $this->queryBus = new QueryBus();
                }
                return $this->queryBus;
            case EventMachine::SERVICE_ID_SNAPSHOT_STORE:
                return $this->getSnapshotStore();
            case EventMachine::SERVICE_ID_PROJECTION_MANAGER:
                if(null === $this->projectionManager) {
                    $this->projectionManager = new InMemoryProjectionManager($this->get(EventMachine::SERVICE_ID_EVENT_STORE));
                }
                return $this->projectionManager;
            case EventMachine::SERVICE_ID_DOCUMENT_STORE:
                if(null === $this->documentStore) {
                    $this->documentStore = new InMemoryDocumentStore();
                }
                return $this->documentStore;
            default:
                if(!array_key_exists($id, $this->services)) {
                    throw ServiceNotFound::withServiceId($id);
                }

                return $this->services[$id];
        }
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id)
    {
        switch ($id) {
            case EventMachine::SERVICE_ID_SNAPSHOT_STORE:
            case EventMachine::SERVICE_ID_EVENT_STORE:
            case EventMachine::SERVICE_ID_COMMAND_BUS:
            case EventMachine::SERVICE_ID_EVENT_BUS:
            case EventMachine::SERVICE_ID_QUERY_BUS:
            case EventMachine::SERVICE_ID_PROJECTION_MANAGER:
            case EventMachine::SERVICE_ID_DOCUMENT_STORE:
                return true;
            default:
                return array_key_exists($id, $this->services);
        }
    }

    private function getSnapshotStore(): SnapshotStore
    {
        if(null === $this->snapshotStore) {
            $this->snapshotStore = new class implements SnapshotStore
            {
                public function get(string $aggregateType, string $aggregateId): ?Snapshot
                {
                    return null;
                }

                public function save(Snapshot ...$snapshots): void
                {
                    // NoOp
                }

                public function removeAll(string $aggregateType): void
                {
                    // NoOp
                }
            };
        }

        return $this->snapshotStore;
    }
}
