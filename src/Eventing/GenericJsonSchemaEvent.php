<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Eventing;

use Prooph\Common\Messaging\DomainMessage;
use Prooph\EventMachine\Messaging\GenericJsonSchemaMessage;

final class GenericJsonSchemaEvent extends GenericJsonSchemaMessage
{
    /**
     * Should be one of Message::TYPE_COMMAND, Message::TYPE_EVENT or Message::TYPE_QUERY
     */
    public function messageType(): string
    {
        return DomainMessage::TYPE_EVENT;
    }

    public function version(): int
    {
        return $this->metadata['_aggregate_version'] ?? 0;
    }
}
