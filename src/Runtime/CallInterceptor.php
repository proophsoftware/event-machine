<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Runtime;

use Prooph\EventMachine\Messaging\Message;

interface CallInterceptor
{
    /**
     * @param Message $command
     * @param mixed $preProcessor A callable or object pulled from app container
     * @return Message
     */
    public function callCommandPreProcessor($preProcessor, Message $command): Message;

    /**
     * Invoked after CommandPreProcessor to load aggregate in case it should exist
     *
     * @param string $aggregateIdPayloadKey
     * @param Message $command
     * @return string
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, Message $command): string;

    /**
     * @param Message $command
     * @param mixed $contextProvider A callable or object pulled from app container
     * @return mixed Context that gets passed as argument to corresponding aggregate function
     */
    public function callContextProvider($contextProvider, Message $command);

    /**
     * @param string $aggregateType
     * @param callable $aggregateFunction
     * @param Message $command
     * @param null|mixed $context
     * @return \Generator Message[] yield events
     */
    public function callFirstAggregateFunction(string $aggregateType, callable $aggregateFunction, Message $command, $context = null): \Generator;

    /**
     * @param string $aggregateType
     * @param callable $aggregateFunction
     * @param mixed $aggregateState
     * @param Message $command
     * @param null|mixed $context
     * @return \Generator Message[] yield events
     */
    public function callSubsequentAggregateFunction(string $aggregateType, callable $aggregateFunction, $aggregateState, Message $command, $context = null): \Generator;

    /**
     * @param callable $applyFunction
     * @param Message $event
     * @return mixed New aggregate state
     */
    public function callApplyFirstEvent(callable $applyFunction, Message $event);

    /**
     * @param callable $applyFunction
     * @param mixed $aggregateState
     * @param Message $event
     * @return mixed Modified aggregae state
     */
    public function callApplySubsequentEvent(callable $applyFunction, $aggregateState, Message $event);

    /**
     * Use this hook to convert a custom message decorated by a MessageBag into an Event Machine message (serialize payload)
     *
     * @param Message $message
     * @return Message
     */
    public function prepareNetworkTransmission(Message $message): Message;

    /**
     * Use this hook to convert an Event Machine message into a custom message and decorate it with a MessageBag
     *
     * Always invoked after raw message data is deserialized into Event Machine Message:
     *
     * - EventMachine::dispatch() is called
     * - EventMachine::messageFactory()->createMessageFromArray() is called
     *
     * Create a type safe message from given Event Machine message and put it into a Prooph\EventMachine\Messaging\MessageBag
     * to pass it through the Event Machine layer.
     *
     * Use MessageBag::get(MessageBag::MESSAGE) in call-interceptions to access your type safe message.
     *
     * @param Message $message
     * @param bool $receivedFromEventStore
     * @return Message
     */
    public function convertMessageReceivedFromNetwork(Message $message, $receivedFromEventStore = false): Message;
}
