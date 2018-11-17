<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Runtime\Functional;

use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Messaging\MessageBag;

interface Port
{
    /**
     * @param Message $message
     * @return mixed The custom message
     */
    public function deserialize(Message $message);

    /**
     * @param mixed $customMessage
     * @return array
     */
    public function serializePayload($customMessage): array;

    /**
     * @param mixed $customEvent
     * @return MessageBag
     */
    public function decorateEvent($customEvent): MessageBag;

    /**
     * @param string $aggregateIdPayloadKey
     * @param mixed $command
     * @return string
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, $command): string;

    /**
     * @param mixed $customCommand
     * @param mixed $preProcessor Custom preprocessor
     * @return mixed Custom message
     */
    public function callCommandPreProcessor($customCommand, $preProcessor);

    /**
     * @param mixed $customCommand
     * @param mixed $contextProvider
     * @return mixed
     */
    public function callContextProvider($customCommand, $contextProvider);
}
