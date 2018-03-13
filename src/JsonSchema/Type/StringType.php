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

use Prooph\EventMachine\JsonSchema\AnnotatedType;
use Prooph\EventMachine\JsonSchema\JsonSchema;

class StringType implements AnnotatedType
{
    use NullableType,
        HasAnnotations;

    private $type = JsonSchema::TYPE_STRING;

    /**
     * @var array
     */
    private $validation = [];

    public function __construct(array $validation = null)
    {
        $this->validation = (array) $validation;
    }

    public function withMinLength(int $minLength): self
    {
        $cp = clone $this;
        $cp->validation['minLength'] = $minLength;

        return $cp;
    }

    public function withPattern(string $pattern): self
    {
        $cp = clone $this;
        $cp->validation['pattern'] = $pattern;

        return $cp;
    }

    public function toArray(): array
    {
        return array_merge(['type' => $this->type], $this->validation, $this->annotations());
    }
}
