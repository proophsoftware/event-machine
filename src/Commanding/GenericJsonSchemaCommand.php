<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Commanding;

use Prooph\Common\Messaging\DomainMessage;
use Prooph\EventMachine\Messaging\GenericJsonSchemaMessage;

final class GenericJsonSchemaCommand extends GenericJsonSchemaMessage
{
    /**
     * Should be one of Message::TYPE_COMMAND, Message::TYPE_EVENT or Message::TYPE_QUERY
     */
    public function messageType(): string
    {
        return DomainMessage::TYPE_COMMAND;
    }
}
