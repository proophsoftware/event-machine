<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Messaging;

use Prooph\Common\Messaging\DomainMessage;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;

abstract class GenericJsonSchemaMessage extends DomainMessage
{
    /**
     * @var array
     */
    private $payload;

    public function __construct(
        string $messageName,
        array $payload,
        array $payloadSchema,
        JsonSchemaAssertion $jsonSchemaAssertion
    )
    {
        self::assertMessageName($messageName);
        $this->messageName = $messageName;
        $jsonSchemaAssertion->assert($messageName, $payload, $payloadSchema);
        $this->init();
        $this->setPayload($payload);
    }

    protected function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function get(string $key)
    {
        if(!array_key_exists($key, $this->payload)) {
            throw new \BadMethodCallException("Message payload of {$this->messageName()} does not contain a key $key.");
        }

        return $this->payload[$key];
    }

    public function getOrDefault(string $key, $default)
    {
        if(!array_key_exists($key, $this->payload)) {
            return $default;
        }

        return $this->payload[$key];
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public static function assertMessageName(string $messageName)
    {
        if(!preg_match('/^[A-Za-z0-9_.-\/]+$/', $messageName)) {
            throw new \InvalidArgumentException("Invalid message name.");
        }
    }
}


