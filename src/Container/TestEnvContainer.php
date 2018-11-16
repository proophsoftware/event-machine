<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Container;

use Prooph\Common\Event\ProophActionEventEmitter;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Persistence\DocumentStore\InMemoryDocumentStore;
use Prooph\EventMachine\Persistence\InMemoryConnection;
use Prooph\EventMachine\Persistence\InMemoryEventStore;
use Prooph\EventMachine\Persistence\TransactionManager;
use Prooph\EventMachine\Projecting\InMemory\InMemoryProjectionManager;
use Prooph\EventStore\ActionEventEmitterEventStore;
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

    private $writeModelStreamName;

    /**
     * @var InMemoryConnection
     */
    private $inMemoryConnection;

    /**
     * @var TransactionManager
     */
    private $transactionManager;

    /**
     * @var array
     */
    private $services;

    public function __construct(array $services, string $writeModelStreamName)
    {
        $this->services = $services;
        $this->writeModelStreamName = $writeModelStreamName;
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
                if (null === $this->eventStore) {
                    $es = new InMemoryEventStore($this->getInMemoryConnection());
                    $es->create(new Stream(new StreamName($this->writeModelStreamName), new \ArrayIterator([])));
                    $this->eventStore = new ActionEventEmitterEventStore($es, new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS));
                }

                return $this->eventStore;
            case EventMachine::SERVICE_ID_COMMAND_BUS:
                if (null === $this->commandBus) {
                    $this->commandBus = new CommandBus();
                }

                return $this->commandBus;
            case EventMachine::SERVICE_ID_EVENT_BUS:
                if (null === $this->eventBus) {
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
                if (null === $this->projectionManager) {
                    $this->projectionManager = new InMemoryProjectionManager(
                        $this->get(EventMachine::SERVICE_ID_EVENT_STORE),
                        $this->getInMemoryConnection()
                    );
                }

                return $this->projectionManager;
            case EventMachine::SERVICE_ID_DOCUMENT_STORE:
                if (null === $this->documentStore) {
                    $this->documentStore = new InMemoryDocumentStore($this->getInMemoryConnection());
                }

                return $this->documentStore;
            case EventMachine::SERVICE_ID_TRANSACTION_MANAGER:
                if (null === $this->transactionManager) {
                    $this->transactionManager = new TransactionManager($this->getInMemoryConnection());
                }

                return $this->transactionManager;
            default:
                if (! \array_key_exists($id, $this->services)) {
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
            case EventMachine::SERVICE_ID_TRANSACTION_MANAGER:
                return true;
            default:
                return \array_key_exists($id, $this->services);
        }
    }

    private function getSnapshotStore(): SnapshotStore
    {
        if (null === $this->snapshotStore) {
            $this->snapshotStore = new class() implements SnapshotStore {
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

    private function getInMemoryConnection(): InMemoryConnection
    {
        if (null === $this->inMemoryConnection) {
            $this->inMemoryConnection = new InMemoryConnection();
        }

        return $this->inMemoryConnection;
    }
}
