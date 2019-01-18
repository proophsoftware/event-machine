<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Messaging;

use DateTimeImmutable;
use Prooph\Common\Messaging\Message as ProophMessage;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * The MessageBag can be used to pass an arbitrary message through the Event Machine layer
 *
 * Class MessageBag
 * @package Prooph\EventMachine\Messaging
 */
final class MessageBag implements Message
{
    public const MESSAGE = 'message';

    /**
     * @var string
     */
    private $messageName;

    /**
     * @var string
     */
    private $messageType;

    /**
     * @var UuidInterface
     */
    private $messageId;

    /**
     * @var mixed
     */
    private $message;

    /**
     * @var array
     */
    private $metadata;

    /**
     * @var \DateTimeImmutable
     */
    private $createdAt;

    private $replacedPayload = false;

    private $payload;

    private const MSG_TYPES = [
        Message::TYPE_COMMAND, Message::TYPE_EVENT, Message::TYPE_QUERY,
    ];

    public function __construct(string $messageName, string $messageType, $message, $metadata = [], UuidInterface $messageId = null, DateTimeImmutable $createdAt = null)
    {
        if (! \in_array($messageType, self::MSG_TYPES)) {
            throw new \InvalidArgumentException('Message type should be one of ' . \implode(', ', self::MSG_TYPES) . ". Got $messageType");
        }

        $this->messageName = $messageName;
        $this->messageId = $messageId ?? Uuid::uuid4();
        $this->messageType = $messageType;
        $this->message = $message;
        $this->metadata = $metadata;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function messageName(): string
    {
        return $this->messageName;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function version(): int
    {
        return $this->metadata['_aggregate_version'] ?? 0;
    }

    /**
     * Get $key from message payload or default in case key does not exist
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOrDefault(string $key, $default)
    {
        if ($this->replacedPayload) {
            if (! \array_key_exists($key, $this->payload)) {
                return $default;
            }

            return $this->payload[$key];
        }

        if ($key === self::MESSAGE) {
            return $this->message;
        }

        return $default;
    }

    /**
     * Should be one of Message::TYPE_COMMAND, Message::TYPE_EVENT or Message::TYPE_QUERY
     */
    public function messageType(): string
    {
        return $this->messageType;
    }

    public function payload(): array
    {
        if ($this->replacedPayload) {
            return $this->payload;
        }

        return [self::MESSAGE => \json_decode(\json_encode($this->message), true)];
    }

    /**
     * Returns new instance of message with $key => $value added to metadata
     *
     * Given value must have a scalar or array type.
     */
    public function withAddedMetadata(string $key, $value): ProophMessage
    {
        $copy = clone $this;
        $copy->metadata[$key] = $value;

        return $copy;
    }

    /**
     * Get $key from message payload
     *
     * @param string $key
     * @throws \BadMethodCallException if key does not exist in payload
     * @return mixed
     */
    public function get(string $key)
    {
        if ($this->replacedPayload) {
            if (! \array_key_exists($key, $this->payload)) {
                throw new \BadMethodCallException("Message payload of {$this->messageName()} does not contain a key $key.");
            }

            return $this->payload[$key];
        }

        if ($key !== self::MESSAGE) {
            throw new \BadMethodCallException(__CLASS__ . ' payload only contains a ' . self::MESSAGE . ' key.');
        }

        return $this->message;
    }

    public function hasMessage(): bool
    {
        return ! $this->replacedPayload;
    }

    public function uuid(): UuidInterface
    {
        return $this->messageId;
    }

    public function withMetadata(array $metadata): ProophMessage
    {
        $copy = clone $this;
        $copy->metadata = $metadata;

        return $copy;
    }

    public function withMessage($message): MessageBag
    {
        $copy = clone $this;
        $copy->message = $message;
        $copy->replacedPayload = false;
        $copy->payload = null;

        return $copy;
    }

    public function withPayload(array $payload, JsonSchemaAssertion $assertion, array $payloadSchema): Message
    {
        $assertion->assert($this->messageName, $payload, $payloadSchema);

        $copy = clone $this;
        $copy->message = null;
        $copy->replacedPayload = true;
        $copy->payload = $payload;

        return $copy;
    }
}
