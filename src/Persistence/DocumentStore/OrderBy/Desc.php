<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore\OrderBy;

final class Desc implements OrderBy
{
    private $field;

    public static function byField(string $field): Desc
    {
        return self::fromString($field);
    }

    public static function fromArray(array $data): OrderBy
    {
        return self::fromString($data['field'] ?? '');
    }

    public static function fromString(string $field): self
    {
        return new self($field);
    }

    private function __construct(string $field)
    {
        if(strlen($field) === 0) {
            throw new \InvalidArgumentException("Field must not be an empty string");
        }
        $this->field = $field;
    }

    public function field(): string
    {
        return $this->field;
    }

    public function toString(): string
    {
        return $this->field;
    }

    public function toArray(): array
    {
        return [
            'field' => $this->field
        ];
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->field === $other->field;
    }

    public function __toString(): string
    {
        return $this->field;
    }
}
