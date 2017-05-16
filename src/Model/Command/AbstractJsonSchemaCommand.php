<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Command;

use Prooph\Common\Messaging\Command;
use Prooph\Common\Messaging\PayloadTrait;
use Prooph\Workshop\Infrastructure\JsonSchema\WebmozartJsonSchemaAssertion;
use Prooph\Workshop\Infrastructure\Util\MessageName;
use Prooph\Workshop\Model\JsonSchema\JsonSchemaAssertion;

abstract class AbstractJsonSchemaCommand extends Command
{
    use PayloadTrait;

    /**
     * @var JsonSchemaAssertion
     */
    private $jsonValidator;

    /**
     * @return array
     */
    abstract function jsonSchema(): array;

    public function messageName(): string
    {
        return MessageName::toMessageName(get_called_class());
    }

    protected function setPayload(array $payload): void
    {
        $this->getJsonValidator()->assert($payload, $this->jsonSchema());
        $this->payload = $payload;
    }

    private function getJsonValidator(): JsonSchemaAssertion
    {
        if(null === $this->jsonValidator) {
            $this->jsonValidator = new WebmozartJsonSchemaAssertion();
        }

        return $this->jsonValidator;
    }
}
