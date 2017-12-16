<?php

declare(strict_types=1);

namespace Prooph\EventMachine\Commanding;

use Prooph\Common\Messaging\Message;

interface CommandPreProcessor
{
    /**
     * Message will be of type Message::TYPE_COMMAND
     *
     * A PreProcessor can change the message and return the changed version (messages are immutable).
     *
     * @param Message $message
     * @return Message
     */
    public function preProcess(Message $message): Message;
}
