<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema\Type;

use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type;

trait NullableType
{
    public function asNullable(): Type
    {
        $cp = clone $this;

        if (! isset($cp->type)) {
            throw new \RuntimeException('Type cannot be converted to nullable type. No json schema type set for ' . \get_class($this));
        }

        if (! \is_string($cp->type)) {
            throw new \RuntimeException('Type cannot be converted to nullable type. JSON schema type is not a string');
        }

        $cp->type = [$cp->type, JsonSchema::TYPE_NULL];

        return $cp;
    }
}
