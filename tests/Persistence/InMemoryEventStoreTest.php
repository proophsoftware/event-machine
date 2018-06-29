<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Persistence;

use Prooph\EventMachine\Persistence\InMemoryConnection;
use Prooph\EventMachine\Persistence\InMemoryEventStore;
use ProophTest\EventStore\AbstractEventStoreTest;
use ProophTest\EventStore\EventStoreTestStreamTrait;
use ProophTest\EventStore\TransactionalEventStoreTestTrait;

class InMemoryEventStoreTest extends AbstractEventStoreTest
{
    use EventStoreTestStreamTrait;
    use TransactionalEventStoreTestTrait;

    /**
     * @var InMemoryEventStore
     */
    protected $eventStore;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore(new InMemoryConnection());
    }
}
