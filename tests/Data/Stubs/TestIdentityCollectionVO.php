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

final class TestIdentityCollectionVO
{
    /**
     * @var TestIdentityVO[]
     */
    private $identities;

    public static function fromIdentities(TestIdentityVO ...$identities): self
    {
        return new self(...$identities);
    }

    public static function fromArray(array $data): self
    {
        $identities = \array_map(function ($item) {
            return TestIdentityVO::fromArray($item);
        }, $data);

        return new self(...$identities);
    }

    private function __construct(TestIdentityVO ...$identities)
    {
        $this->identities = $identities;
    }

    public function first(): TestIdentityVO
    {
        return $this->identities[0];
    }

    public function toArray(): array
    {
        return \array_map(function (TestIdentityVO $item) {
            return $item->toArray();
        }, $this->identities);
    }

    public function equals($other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    public function __toString(): string
    {
        return \json_encode($this->toArray());
    }
}
