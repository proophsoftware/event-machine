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

class FloatType implements AnnotatedType
{
    use NullableType,
        HasAnnotations;

    /**
     * @var string|array
     */
    private $type = JsonSchema::TYPE_FLOAT;

    /**
     * @var null|array
     */
    private $validation;

    public function __construct(array $validation = null)
    {
        $this->validation = $validation;
    }

    public function toArray(): array
    {
        return \array_merge(['type' => $this->type], (array) $this->validation, $this->annotations());
    }

    public function withMinimum(float $min): self
    {
        $cp = clone $this;

        $validation = (array) $this->validation;

        $validation['minimum'] = $min;

        $cp->validation = $validation;

        return $cp;
    }

    public function withMaximum(float $max): self
    {
        $cp = clone $this;

        $validation = (array) $this->validation;

        $validation['maximum'] = $max;

        $cp->validation = $validation;

        return $cp;
    }

    public function withRange(float $min, float $max): self
    {
        $cp = clone $this;

        $validation = (array) $this->validation;

        $validation['minimum'] = $min;
        $validation['maximum'] = $max;

        $cp->validation = $validation;

        return $cp;
    }
}
