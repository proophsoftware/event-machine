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
use Prooph\EventMachine\Exception\TransactionCommitFailed;
use Prooph\EventMachine\Exception\TransactionNotStarted;
use Prooph\EventMachine\Exception\TransactionRollBackFailed;
use Prooph\EventMachine\Persistence\TransactionalConnection;
use Prooph\EventMachine\Persistence\TransactionManager;

class TransactionManagerTest extends TestCase
{
    /**
     * @test
     */
    public function it_begins_transaction(): void
    {
        $connection = $this->prophesize(TransactionalConnection::class);
        $connection->beginTransaction()->shouldBeCalled();

        $cut = new TransactionManager($connection->reveal());

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Transaction not started');
    }

    /**
     * @test
     */
    public function it_commits_transaction(): void
    {
        $connection = $this->prophesize(TransactionalConnection::class);
        $connection->beginTransaction()->shouldBeCalled();
        $connection->commit()->shouldBeCalled();

        $cut = new TransactionManager($connection->reveal());

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
        $connection = $this->prophesize(TransactionalConnection::class);
        $connection->beginTransaction()->shouldBeCalled();
        $connection->rollBack()->shouldBeCalled();

        $cut = new TransactionManager($connection->reveal());

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Transaction not started');

        $cut->rollBack();
        $this->assertFalse($cut->inTransaction(), 'Transaction not closed');
    }

    /**
     * @test
     */
    public function it_begins_nested_transaction(): void
    {
        $connection = $this->prophesize(TransactionalConnection::class);
        $connection->beginTransaction()->shouldBeCalledTimes(1);

        $cut = new TransactionManager($connection->reveal());

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Transaction not started');

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Nested transaction not started');
    }

    /**
     * @test
     */
    public function it_commits_nested_transaction(): void
    {
        $connection = $this->prophesize(TransactionalConnection::class);
        $connection->beginTransaction()->shouldBeCalledTimes(1);
        $connection->commit()->shouldBeCalledTimes(1);

        $cut = new TransactionManager($connection->reveal());

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Transaction not started');

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Nested transaction not started');

        $cut->commit();
        $this->assertTrue($cut->inTransaction());

        $cut->commit();
        $this->assertFalse($cut->inTransaction(), 'Transaction not committed');
    }

    /**
     * @test
     */
    public function it_rolls_back_nested_transaction(): void
    {
        $connection = $this->prophesize(TransactionalConnection::class);
        $connection->beginTransaction()->shouldBeCalledTimes(1);
        $connection->rollBack()->shouldBeCalledTimes(1);

        $cut = new TransactionManager($connection->reveal());

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Transaction not started');

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Nested transaction not started');

        $cut->rollBack();
        $this->assertTrue($cut->inTransaction());

        $cut->rollBack();
        $this->assertFalse($cut->inTransaction(), 'Transaction not closed');
    }

    /**
     * @test
     */
    public function it_throws_transaction_not_started_exception_on_commit(): void
    {
        $connection = $this->prophesize(TransactionalConnection::class);
        $cut = new TransactionManager($connection->reveal());

        $this->expectException(TransactionNotStarted::class);
        $cut->commit();
    }

    /**
     * @test
     */
    public function it_throws_transaction_commit_failed_exception_on_commit(): void
    {
        $connection = $this->prophesize(TransactionalConnection::class);
        $connection->beginTransaction()->shouldBeCalledTimes(1);
        $connection->commit()->willThrow(TransactionCommitFailed::with(new \RuntimeException('test')));
        $cut = new TransactionManager($connection->reveal());

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Transaction not started');

        $this->expectException(TransactionCommitFailed::class);
        $cut->commit();
    }

    /**
     * @test
     */
    public function it_throws_transaction_not_started_exception_on_roll_back(): void
    {
        $connection = $this->prophesize(TransactionalConnection::class);
        $cut = new TransactionManager($connection->reveal());

        $this->expectException(TransactionNotStarted::class);
        $cut->rollBack();
    }

    /**
     * @test
     */
    public function it_throws_transaction_roll_back_failed_exception_on_roll_back(): void
    {
        $connection = $this->prophesize(TransactionalConnection::class);
        $connection->beginTransaction()->shouldBeCalledTimes(1);
        $connection->rollBack()->willThrow(TransactionRollBackFailed::with(new \RuntimeException('test')));
        $cut = new TransactionManager($connection->reveal());

        $cut->beginTransaction();
        $this->assertTrue($cut->inTransaction(), 'Transaction not started');

        $this->expectException(TransactionRollBackFailed::class);
        $cut->rollBack();
    }
}
