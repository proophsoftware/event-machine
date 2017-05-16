<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Event;


use Prooph\Workshop\Model\User\UserId;

final class UserWasRegistered extends DomainEvent
{
    public function userId(): UserId
    {
        return UserId::fromString($this->aggregateId());
    }

    public function username(): string
    {
        return $this->payload['username'];
    }

    public function email(): string
    {
        return $this->payload['email'];
    }
}
