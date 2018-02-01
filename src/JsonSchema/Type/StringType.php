<?php

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema\Type;

use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type;

class StringType implements Type
{
    use NullableType;

    private $type = JsonSchema::TYPE_STRING;

    /**
     * @var array
     */
    private $validation = [];

    public function __construct(array $validation = null)
    {
        $this->validation = (array)$validation;
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
        return array_merge(['type' => $this->type], $this->validation);
    }
}
