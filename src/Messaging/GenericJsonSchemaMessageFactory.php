<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Messaging;

use Fig\Http\Message\StatusCodeInterface;
use Prooph\Common\Messaging\DomainMessage;
use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Commanding\GenericJsonSchemaCommand;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
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

    public function __construct(array $commandMap, array $eventMap, JsonSchemaAssertion $jsonSchemaAssertion)
    {
        $this->jsonSchemaAssertion = $jsonSchemaAssertion;
        $this->commandMap = $commandMap;
        $this->eventMap = $eventMap;
        //@@TODO: Add optional metadata schema that is then used to validate metadata of all messages
    }

    /**
     * @inheritdoc
     */
    public function createMessageFromArray(string $messageName, array $messageData): Message
    {
        $messageType = null;
        $payloadSchema = null;

        GenericJsonSchemaMessage::assertMessageName($messageName);

        if(array_key_exists($messageName, $this->commandMap)) {
            $messageType = DomainMessage::TYPE_COMMAND;
            $payloadSchema = $this->commandMap[$messageName];
        }

        if($messageType === null && array_key_exists($messageName, $this->eventMap)) {
            $messageType = DomainMessage::TYPE_EVENT;
            $payloadSchema = $this->eventMap[$messageName];
        }

        if(null === $messageType) {
            throw new \RuntimeException(
                "Unknown message received. Got message with name: $messageName",
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

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
                return GenericJsonSchemaCommand::fromArray($messageData);
            case DomainMessage::TYPE_EVENT:
                return GenericJsonSchemaEvent::fromArray($messageData);
        }
    }
}
