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

use Prooph\EventMachine\JsonSchema\AnnotatedType;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Ramsey\Uuid\Uuid;

class UuidType implements AnnotatedType
{
    use NullableType,
        HasAnnotations;

    private $type = JsonSchema::TYPE_STRING;

    public function toArray(): array
    {
        return \array_merge([
            'type' => $this->type,
            'pattern' => Uuid::VALID_PATTERN,
        ], $this->annotations());
    }
}
