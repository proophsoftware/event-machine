<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\JsonSchema;

use JsonSchema\Validator;

final class JustinRainbowJsonSchemaAssertion implements JsonSchemaAssertion
{
    private static $jsonValidator;

    public function assert(string $messageName, array $data, array $jsonSchema)
    {
        $enforcedObjectData = json_decode(json_encode($data));
        $jsonSchema = json_decode(json_encode($jsonSchema));

        $this->jsonValidator()->validate($enforcedObjectData, $jsonSchema);

        if(!$this->jsonValidator()->isValid()) {
            $errors = $this->jsonValidator()->getErrors();

            $this->jsonValidator()->reset();

            foreach ($errors as $i => $error) {
                $errors[$i] = sprintf("[%s] %s\n", $error['property'], $error['message']);
            }

            throw new \InvalidArgumentException(
                "Payload validation of $messageName failed: " . implode("\n", $errors),
                400
            );
        }

        $this->jsonValidator()->reset();
        return;
    }

    private function jsonValidator(): Validator
    {
        if (null === self::$jsonValidator) {
            self::$jsonValidator = new Validator();
        }

        return self::$jsonValidator;
    }
}
