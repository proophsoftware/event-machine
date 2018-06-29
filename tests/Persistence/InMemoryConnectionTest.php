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

use PHPUnit\Framework\TestCase;
use Prooph\EventMachine\Persistence\InMemoryConnection;

class InMemoryConnectionTest extends TestCase
{
    /**
     * @test
     */
    public function it_adds_data(): void
    {
        $cut = new InMemoryConnection();

        $cut['event_streams'] = [['one' => 123]];
        $cut['event_streams'][] = ['two' => 2423];

        $this->assertCount(2, $cut['event_streams']);
    }

    /**
     * @test
     */
    public function it_adds_deep_data(): void
    {
        $cut = new InMemoryConnection();

        $cut['event_streams']['awesome']['metadata'] = [['one' => 123]];

        $this->assertCount(1, $cut['event_streams']);
    }

    /**
     * @test
     */
    public function it_adds_data_in_transaction(): void
    {
        $cut = new InMemoryConnection();
        $cut->beginTransaction();

        $cut['event_streams'] = [['one' => 123]];
        $cut['event_streams'][] = ['two' => 2423];

        $cut->commit();
        $this->assertFalse($cut->inTransaction(), 'Transaction not committed');

        $this->assertCount(2, $cut['event_streams']);
    }

    /**
     * @test
     */
    public function it_rolls_back_data(): void
    {
        $cut = new InMemoryConnection();
        $cut->beginTransaction();

        $cut['event_streams'] = [['one' => 123]];
        $cut['event_streams'][] = ['two' => 2423];

        $cut->rollBack();
        $this->assertFalse($cut->inTransaction(), 'Transaction not committed');

        $this->assertEmpty($cut['event_streams']);
    }

    /**
     * @test
     */
    public function it_begins_transaction(): void
    {
        $cut = new InMemoryConnection();

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Transaction not started');
    }

    /**
     * @test
     */
    public function it_commits_transaction(): void
    {
        $cut = new InMemoryConnection();

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Transaction not started');

        $cut->commit();
        $this->assertFalse($cut->inTransaction(), 'Transaction not committed');
    }

    /**
     * @test
     */
    public function it_rolls_back_transaction(): void
    {
        $cut = new InMemoryConnection();

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Transaction not started');

        $cut->rollBack();
        $this->assertFalse($cut->inTransaction(), 'Transaction not closed');
    }
}
