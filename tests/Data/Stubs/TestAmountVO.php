<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Data\Stubs;

final class TestAmountVO
{
    private $amount;

    public static function fromFloat(float $amount): self
    {
        return new self($amount);
    }

    private function __construct(float $amount)
    {
        $this->amount = $amount;
    }

    public function toFloat(): float
    {
        return $this->amount;
    }

    public function equals($other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->amount === $other->amount;
    }

    public function __toString(): string
    {
        return (string) $this->amount;
    }
}
