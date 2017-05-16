<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Configuration;


final class Node
{
    /**
     * @var bool
     */
    private $hasStart;

    /**
     * @var bool
     */
    private $hasEnd;

    public static function asStartNode(): Node
    {
        return new self(true, false);
    }

    public static function asEndNode(): Node
    {
        return new self(false, true);
    }

    public static function asNode(): Node
    {
        return new self(true, true);
    }

    public static function fromArray(array $data): Node
    {
        return new self($data['hasStart'], $data['hasEnd']);
    }

    private function __construct(bool $hasStart, bool $hasEnd)
    {
        $this->hasStart = $hasStart;
        $this->hasEnd = $hasEnd;
    }

    public function isStartNode(): bool
    {
        return $this->hasStart === true && $this->hasEnd === false;
    }

    public function isEndNode(): bool
    {
        return $this->hasStart === false && $this->hasEnd === true;
    }

    public function isMiddleNode(): bool
    {
        return $this->hasStart === true && $this->hasEnd === true;
    }

    public function toArray(): array
    {
        return [
            'hasStart' => $this->hasStart,
            'hasEnd' => $this->hasEnd
        ];
    }
}
