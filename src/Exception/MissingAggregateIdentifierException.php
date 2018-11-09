<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Exception;

use Prooph\EventMachine\Messaging\Message;

final class MissingAggregateIdentifierException extends InvalidArgumentException
{
    public static function inCommand(Message $command, string $aggregateIdPayloadKey): self
    {
        return new self(sprintf(
            'Missing aggregate identifier %s in payload of command %s',
            $aggregateIdPayloadKey,
            $command->messageName()
        ));
    }
}
