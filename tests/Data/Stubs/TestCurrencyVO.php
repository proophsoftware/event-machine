<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        if (! $other instanceof self) {
            return false;
        }

        return $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return $this->currency;
    }
}
