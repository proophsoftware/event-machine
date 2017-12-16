<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\Data\Stubs;

final class TestCurrencyVO
{
    private $currency;

    public static function fromString(string $currency): self
    {
        return new self($currency);
    }

    private function __construct(string $currency)
    {
        $this->currency = $currency;
    }

    public function toString(): string
    {
        return $this->currency;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return $this->currency;
    }
}
