<?php

declare(strict_types = 1);

namespace Prooph\Workshop\Model\User;

use Prooph\Workshop\Model\User;

interface UserRepository
{
    public function get(UserId $userId): User;

    public function save(User $user): void;
}
