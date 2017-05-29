<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Aggregate\Exception;

use Fig\Http\Message\StatusCodeInterface;

final class AggregateNotFound extends \RuntimeException
{
    public static function with(string $aggregateType, string $aggregateId): self
    {
        return new self(sprintf(
            "Aggregate of type %s with id %s not found.",
            $aggregateType,
            $aggregateId
        ), StatusCodeInterface::STATUS_NOT_FOUND);
    }
}
