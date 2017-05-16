<?php
declare(strict_types = 1);

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
