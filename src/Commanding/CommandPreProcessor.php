<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
