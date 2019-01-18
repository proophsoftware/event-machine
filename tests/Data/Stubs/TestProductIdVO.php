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

final class TestProductIdVO
{
    private $productId;

    public static function fromInt(int $productId): self
    {
        return new self($productId);
    }

    private function __construct(int $productId)
    {
        $this->productId = $productId;
    }

    public function toInt(): int
    {
        return $this->productId;
    }

    public function equals($other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->productId === $other->productId;
    }

    public function __toString(): string
    {
        return (string) $this->productId;
    }
}
