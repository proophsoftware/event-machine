<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Event;

use Prooph\Workshop\Model\User\UserId;

final class UsernameWasChanged extends DomainEvent
{
    public function userId(): UserId
    {
        return UserId::fromString($this->aggregateId());
    }

    public function oldUsername(): string
    {
        return $this->payload['oldName'];
    }

    public function newUsername(): string
    {
        return $this->payload['newName'];
    }
}
