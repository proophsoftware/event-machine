<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\Data\Stubs;

final class TestProductActiveFlagVO
{
    private $active;

    public static function fromBool(bool $active): self
    {
        return new self($active);
    }

    private function __construct(bool $active)
    {
        $this->active = $active;
    }

    public function toBool(): bool
    {
        return $this->active;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->active === $other->active;
    }

    public function __toString(): string
    {
        return $this->active ? 'TRUE' : 'FALSE';
    }
}
