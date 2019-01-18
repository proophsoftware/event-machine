<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore\OrderBy;

final class Desc implements OrderBy
{
    /**
     * Nested props are accessed using dot notation
     *
     * @var string
     */
    private $prop;

    public static function byProp(string $prop): Desc
    {
        return self::fromString($prop);
    }

    public static function fromArray(array $data): OrderBy
    {
        return self::fromString($data['prop'] ?? '');
    }

    public static function fromString(string $field): self
    {
        return new self($field);
    }

    private function __construct(string $prop)
    {
        if (\strlen($prop) === 0) {
            throw new \InvalidArgumentException('Prop must not be an empty string');
        }
        $this->prop = $prop;
    }

    public function prop(): string
    {
        return $this->prop;
    }

    public function toString(): string
    {
        return $this->prop;
    }

    public function toArray(): array
    {
        return [
            'prop' => $this->prop,
        ];
    }

    public function equals($other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->prop === $other->prop;
    }

    public function __toString(): string
    {
        return $this->prop;
    }
}
