<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema\Type;

use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\AnnotatedType;

class EnumType implements AnnotatedType
{
    use NullableType,
        HasAnnotations;

    /**
     * @var string|array
     */
    private $type = JsonSchema::TYPE_STRING;

    /**
     * @var string[]
     */
    private $entries;

    public function __construct(string ...$entries)
    {
        $this->entries = $entries;
    }

    public function toArray(): array
    {
        return array_merge([
            'type' => $this->type,
            'enum' => $this->entries,
        ], $this->annotations());
    }
}
