<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Exception;

use Prooph\EventMachine\Messaging\Message;

final class NoGeneratorException extends InvalidArgumentException
{
    public static function forAggregateTypeAndCommand(string $aggregateType, Message $command): self
    {
        return new self('Expected aggregateFunction to be of type Generator. ' .
            'Did you forget the yield keyword in your command handler?' .
            "Tried to handle command {$command->messageName()} for aggregate {$aggregateType}"
        );
    }
}
