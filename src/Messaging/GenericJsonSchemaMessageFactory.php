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

use Fig\Http\Message\StatusCodeInterface;
use Prooph\Common\Messaging\DomainMessage;
use Prooph\Common\Messaging\Message as ProophMessage;
use Prooph\EventMachine\Commanding\GenericJsonSchemaCommand;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Prooph\EventMachine\Querying\GenericJsonSchemaQuery;
use Prooph\EventMachine\Runtime\Flavour;
use Ramsey\Uuid\Uuid;

final class GenericJsonSchemaMessageFactory implements MessageFactory
{
    /**
     * @var JsonSchemaAssertion
     */
    private $jsonSchemaAssertion;

    /**
     * Map of command names and corresponding json schema of payload
     *
     * Json schema can be passed as array or path to schema file
     *
     * @var array
     */
    private $commandMap = [];

    /**
     * Map of event names and corresponding json schema of payload
     *
     * Json schema can be passed as array or path to schema file
     *
     * @var array
     */
    private $eventMap = [];

    /**
     * Map of query names and corresponding json schema of payload
     *
     * Json schema can be passed as array or path to schema file
     *
     * @var array
     */
    private $queryMap = [];

    /**
     * Map of type definitions used within other json schemas.
     *
     * @var array
     */
    private $definitions = [];

    /**
     * @var Flavour
     */
    private $flavour;

    public function __construct(array $commandMap, array $eventMap, array $queryMap, array $definitions, JsonSchemaAssertion $jsonSchemaAssertion)
    {
        $this->jsonSchemaAssertion = $jsonSchemaAssertion;
        $this->commandMap = $commandMap;
        $this->eventMap = $eventMap;
        $this->queryMap = $queryMap;
        $this->definitions = $definitions;
        //@@TODO: Add optional metadata schema that is then used to validate metadata of all messages
    }

    /**
     * {@inheritdoc}
     */
    public function createMessageFromArray(string $messageName, array $messageData): ProophMessage
    {
        GenericJsonSchemaMessage::assertMessageName($messageName);

        [$messageType, $payloadSchema] = $this->getPayloadSchemaAndMessageType($messageName);

        if (! isset($messageData['payload'])) {
            $messageData['payload'] = [];
        }

        $this->jsonSchemaAssertion->assert($messageName, $messageData['payload'], $payloadSchema);

        $messageData['message_name'] = $messageName;

        if (! isset($messageData['uuid'])) {
            $messageData['uuid'] = Uuid::uuid4();
        }

        if (! isset($messageData['created_at'])) {
            $messageData['created_at'] = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }

        if (! isset($messageData['metadata'])) {
            $messageData['metadata'] = [];
        }

        switch ($messageType) {
            case DomainMessage::TYPE_COMMAND:
                $message = GenericJsonSchemaCommand::fromArray($messageData);
                break;
            case DomainMessage::TYPE_EVENT:
                $message = GenericJsonSchemaEvent::fromArray($messageData);
                break;
            case DomainMessage::TYPE_QUERY:
                $message = GenericJsonSchemaQuery::fromArray($messageData);
                break;
        }

        if ($this->flavour) {
            return $this->flavour->convertMessageReceivedFromNetwork($message);
        }

        return $message;
    }

    public function setFlavour(Flavour $flavour): void
    {
        $this->flavour = $flavour;
    }

    public function setPayloadFor(Message $message, array $payload): Message
    {
        [, $payloadSchema] = $this->getPayloadSchemaAndMessageType($message->messageName());

        return $message->withPayload($payload, $this->jsonSchemaAssertion, $payloadSchema);
    }

    private function getPayloadSchemaAndMessageType(string $messageName): array
    {
        $payloadSchema = null;
        $messageType = null;

        if (\array_key_exists($messageName, $this->commandMap)) {
            $messageType = DomainMessage::TYPE_COMMAND;
            $payloadSchema = $this->commandMap[$messageName];
        }

        if ($messageType === null && \array_key_exists($messageName, $this->eventMap)) {
            $messageType = DomainMessage::TYPE_EVENT;
            $payloadSchema = $this->eventMap[$messageName];
        }

        if ($messageType === null && \array_key_exists($messageName, $this->queryMap)) {
            $messageType = DomainMessage::TYPE_QUERY;
            $payloadSchema = $this->queryMap[$messageName];
        }

        if (null === $messageType) {
            throw new RuntimeException(
                "Unknown message received. Got message with name: $messageName",
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        if (null === $payloadSchema && $messageType === DomainMessage::TYPE_QUERY) {
            $payloadSchema = [];
        }

        $payloadSchema['definitions'] = $this->definitions;

        return [$messageType, $payloadSchema];
    }
}
