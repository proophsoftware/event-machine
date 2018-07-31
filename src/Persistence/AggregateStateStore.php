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

use Prooph\EventMachine\Aggregate\Exception\AggregateNotFound;
use Prooph\EventMachine\Exception\InvalidArgumentException;

interface AggregateStateStore
{
    /**
     * @param string $aggregateType
     * @param string $aggregateId
     * @return mixed State of the aggregate
     * @throws InvalidArgumentException If $aggregateType is unknown
     * @throws AggregateNotFound If aggregate state cannot be found
     */
    public function loadAggregateState(string $aggregateType, string $aggregateId);
}
