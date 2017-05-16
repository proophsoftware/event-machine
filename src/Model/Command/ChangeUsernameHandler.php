<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Command;

use Prooph\Workshop\Model\User\UserRepository;

final class ChangeUsernameHandler
{
    /**
     * @var UserRepository
     */
    private $userRepo;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepo = $userRepository;
    }

    public function __invoke(ChangeUsername $command): void
    {
        $user = $this->userRepo->get($command->userId());

        $user->changeUsername($command->username());

        $this->userRepo->save($user);
    }
}
