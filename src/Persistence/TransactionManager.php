<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Persistence;

use Prooph\EventMachine\Exception\TransactionCommitFailed;
use Prooph\EventMachine\Exception\TransactionNotStarted;
use Prooph\EventMachine\Exception\TransactionRollBackFailed;
use Prooph\ServiceBus\MessageBus;
use React\Promise\Promise;

final class TransactionManager
{
    /**
     * @var TransactionalConnection
     */
    private $connection;

    /**
     * @var int
     */
    private $transactionNestingLevel = 0;

    public function __construct(TransactionalConnection $connection)
    {
        $this->connection = $connection;
    }

    public function dispatch(MessageBus $bus, $message): ?Promise
    {
        return $bus->dispatch($message);
    }

    public function beginTransaction(): void
    {
        ++$this->transactionNestingLevel;

        if ($this->transactionNestingLevel === 1) {
            $this->connection->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->transactionNestingLevel === 0) {
            throw TransactionNotStarted::commit();
        }

        try {
            if ($this->transactionNestingLevel === 1) {
                $this->connection->commit();
            }
        } catch (\Throwable $e) {
            throw TransactionCommitFailed::with($e);
        } finally {
            --$this->transactionNestingLevel;
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionNestingLevel === 0) {
            throw TransactionNotStarted::rollback();
        }

        try {
            if ($this->transactionNestingLevel === 1) {
                $this->connection->rollBack();
            }
        } catch (\Throwable $e) {
            throw TransactionRollBackFailed::with($e);
        } finally {
            --$this->transactionNestingLevel;
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactionNestingLevel > 0;
    }
}
