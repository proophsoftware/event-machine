<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\User;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UserId
{
    /**
     * @var UuidInterface
     */
    private $id;

    public static function fromString(string $uuid): UserId
    {
        return new self(Uuid::fromString($uuid));
    }

    private function __construct(UuidInterface $uuid)
    {
        $this->id = $uuid;
    }

    public function toString(): string
    {
        return $this->id->toString();
    }
}
