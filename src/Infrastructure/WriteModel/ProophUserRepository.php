<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Infrastructure\WriteModel;

use Prooph\EventSourcing\Aggregate\AggregateRepository;
use Prooph\Workshop\Model\User;
use Prooph\Workshop\Model\User\UserId;
use Prooph\Workshop\Model\User\UserRepository;

final class ProophUserRepository extends AggregateRepository implements UserRepository
{

    public function get(UserId $userId): User
    {
        return $this->getAggregateRoot($userId->toString());
    }

    public function save(User $user): void
    {
        $this->saveAggregateRoot($user);
    }
}
