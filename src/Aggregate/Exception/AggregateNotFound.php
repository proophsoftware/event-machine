<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Aggregate\Exception;

use Fig\Http\Message\StatusCodeInterface;

final class AggregateNotFound extends \RuntimeException
{
    public static function with(string $aggregateType, string $aggregateId): self
    {
        return new self(\sprintf(
            'Aggregate of type %s with id %s not found.',
            $aggregateType,
            $aggregateId
        ), StatusCodeInterface::STATUS_NOT_FOUND);
    }
}
