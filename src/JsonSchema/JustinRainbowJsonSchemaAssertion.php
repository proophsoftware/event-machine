<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema;

use JsonSchema\Validator;

final class JustinRainbowJsonSchemaAssertion implements JsonSchemaAssertion
{
    private static $jsonValidator;

    public function assert(string $objectName, array $data, array $jsonSchema)
    {
        if ($data === [] && JsonSchema::isObjectType($jsonSchema)) {
            $data = new \stdClass();
        }

        $enforcedObjectData = \json_decode(\json_encode($data));
        $jsonSchema = \json_decode(\json_encode($jsonSchema));

        $this->jsonValidator()->validate($enforcedObjectData, $jsonSchema);

        if (! $this->jsonValidator()->isValid()) {
            $errors = $this->jsonValidator()->getErrors();

            $this->jsonValidator()->reset();

            foreach ($errors as $i => $error) {
                $errors[$i] = \sprintf("[%s] %s\n", $error['property'], $error['message']);
            }

            throw new \InvalidArgumentException(
                "Validation of $objectName failed: " . \implode("\n", $errors),
                400
            );
        }

        $this->jsonValidator()->reset();
    }

    private function jsonValidator(): Validator
    {
        if (null === self::$jsonValidator) {
            self::$jsonValidator = new Validator();
        }

        return self::$jsonValidator;
    }
}
