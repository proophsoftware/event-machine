<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Command;


use Prooph\Workshop\Model\Configuration\ConfigurationRepository;
use Prooph\Workshop\Model\User\UserRepository;

final class StartNewConfigHandler
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var ConfigurationRepository
     */
    private $configurationRepository;

    public function __construct(UserRepository $userRepository, ConfigurationRepository $configurationRepository)
    {
        $this->userRepository = $userRepository;
        $this->configurationRepository = $configurationRepository;
    }

    public function __invoke(StartNewConfig $command)
    {
        $user = $this->userRepository->get($command->userId());

        if(!$user) {
            throw new \InvalidArgumentException("User not found with id: " . $command->userId()->toString());
        }

        $configuration = $user->startNewConfiguration(
            $command->configurationId(),
            $command->startNode(),
            $command->endNode()
        );

        $this->configurationRepository->save($configuration);
    }
}
