<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\ProcessManager;


use Prooph\Common\Messaging\MessageFactory;
use Prooph\ServiceBus\CommandBus;
use Prooph\Workshop\Model\Command\StartNewConfig;
use Prooph\Workshop\Model\Configuration\Node;
use Prooph\Workshop\Model\Event\UserWasRegistered;
use Ramsey\Uuid\Uuid;

final class UserConfigurationStarter
{
    /**
     * @var CommandBus
     */
    private $commandBus;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    public function __construct(CommandBus $commandBus, MessageFactory $messageFactory)
    {
        $this->commandBus = $commandBus;
        $this->messageFactory = $messageFactory;
    }

    public function __invoke(UserWasRegistered $userWasRegistered)
    {
        $configurationId = Uuid::uuid4();
        $startNode = Node::asStartNode();
        $endNode = Node::asEndNode();

        $this->commandBus->dispatch(
            $this->messageFactory->createMessageFromArray('StartNewConfig',[
                'payload' => [
                    'configurationId' => $configurationId->toString(),
                    'startNode' => $startNode->toArray(),
                    'endNode' => $endNode->toArray(),
                    'userId' => $userWasRegistered->userId()->toString()
                ],
                'metadata' => [
                    'causation_id' => $userWasRegistered->uuid()->toString()
                ]
            ])
        );
    }
}
