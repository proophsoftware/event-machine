<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore;

final class MultiFieldIndex implements Index
{
    /**
     * @var FieldIndex[]
     */
    private $fields;

    /**
     * @var bool
     */
    private $unique;

    public static function forFields(array $fieldNames, bool $unique = false): self
    {
        return self::fromArray([
            'fields' => $fieldNames,
            'unique' => $unique,
        ]);
    }

    public static function fromArray(array $data): self
    {
        $fields = array_map(function (string $field): FieldIndex {
            return FieldIndex::forFieldInMultiFieldIndex($field);
        }, $data['fields'] ?? []);


        return new self(
            $data['unique'] ?? false,
            ...$fields
        );
    }

    private function __construct(bool $unique, FieldIndex ...$fields)
    {
        if(count($fields) <= 1) {
            throw new \InvalidArgumentException("MultiFieldIndex should contain at least two fields");
        }

        $this->fields = $fields;
        $this->unique = $unique;
    }

    public function toArray(): array
    {
        return [
            'fields' => array_map(function (FieldIndex $field): string {
                return $field->field();
            }, $this->fields),
            'unique' => $this->unique
        ];
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
