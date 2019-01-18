<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Projecting\InMemory;

use Prooph\EventMachine\Persistence\InMemoryConnection;
use Prooph\EventMachine\Persistence\InMemoryEventStore;
use Prooph\EventMachine\Projecting\InMemory\InMemoryProjectionManager;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\EventStoreDecorator;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\RuntimeException;
use ProophTest\EventStore\Projection\AbstractProjectionManagerTest;

class InMemoryProjectionManagerTest extends AbstractProjectionManagerTest
{
    /**
     * @var InMemoryProjectionManager
     */
    protected $projectionManager;

    /**
     * @var InMemoryEventStore
     */
    protected $eventStore;

    /**
     * @var InMemoryConnection
     */
    protected $inMemoryConnection;

    protected function setUp(): void
    {
        $this->inMemoryConnection = new InMemoryConnection();
        $this->eventStore = new InMemoryEventStore($this->inMemoryConnection);
        $this->projectionManager = new InMemoryProjectionManager($this->eventStore, $this->inMemoryConnection);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_invalid_event_store_instance_passed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $eventStore = $this->prophesize(EventStore::class);

        new InMemoryProjectionManager($eventStore->reveal(), $this->inMemoryConnection);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_invalid_wrapped_event_store_instance_passed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $eventStore = $this->prophesize(EventStore::class);
        $wrappedEventStore = $this->prophesize(EventStoreDecorator::class);
        $wrappedEventStore->getInnerEventStore()->willReturn($eventStore->reveal())->shouldBeCalled();

        new InMemoryProjectionManager($wrappedEventStore->reveal(), $this->inMemoryConnection);
    }

    /**
     * @test
     */
    public function it_cannot_delete_projections(): void
    {
        $this->expectException(RuntimeException::class);

        $this->projectionManager->deleteProjection('foo', true);
    }

    /**
     * @test
     */
    public function it_cannot_reset_projections(): void
    {
        $this->expectException(RuntimeException::class);

        $this->projectionManager->resetProjection('foo');
    }

    /**
     * @test
     */
    public function it_cannot_stop_projections(): void
    {
        $this->expectException(RuntimeException::class);

        $this->projectionManager->stopProjection('foo');
    }

    /**
     * @test
     */
    public function it_throws_exception_when_trying_to_delete_non_existing_projection(): void
    {
        $this->markTestSkipped('Deleting a projection is not supported in ' . InMemoryProjectionManager::class);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_trying_to_reset_non_existing_projection(): void
    {
        $this->markTestSkipped('Resetting a projection is not supported in ' . InMemoryProjectionManager::class);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_trying_to_stop_non_existing_projection(): void
    {
        $this->markTestSkipped('Stopping a projection is not supported in ' . InMemoryProjectionManager::class);
    }

    /**
     * @test
     */
    public function it_does_not_fail_deleting_twice(): void
    {
        $this->markTestSkipped('Deleting a projection is not supported in ' . InMemoryProjectionManager::class);
    }

    /**
     * @test
     */
    public function it_does_not_fail_resetting_twice(): void
    {
        $this->markTestSkipped('Resetting a projection is not supported in ' . InMemoryProjectionManager::class);
    }

    /**
     * @test
     */
    public function it_does_not_fail_stopping_twice(): void
    {
        $this->markTestSkipped('Stopping a projection is not supported in ' . InMemoryProjectionManager::class);
    }
}
