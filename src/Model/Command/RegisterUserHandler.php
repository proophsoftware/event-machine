<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Command;

use Prooph\Workshop\Model\User;

final class RegisterUserHandler
{
    /**
     * @var User\UserRepository
     */
    private $userRepo;

    public function __construct(User\UserRepository $userRepository)
    {
        $this->userRepo = $userRepository;
    }

    public function __invoke(RegisterUser $command)
    {
        $user = User::register(
            $command->userId(),
            $command->username(),
            $command->email()
        );

        $this->userRepo->save($user);
    }
}
