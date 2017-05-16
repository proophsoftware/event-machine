<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Event;

use Prooph\Workshop\Model\Configuration\Node;
use Prooph\Workshop\Model\User\UserId;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class NewConfigWasStarted extends DomainEvent
{
    public static function withNodesForUser(Node $startNode, Node $endNode, Uuid $configurationId, UserId $userId): NewConfigWasStarted
    {
        return self::occur($configurationId->toString(), [
            'startNode' => $startNode->toArray(),
            'endNode' => $endNode->toArray(),
            'userId' => $userId->toString()
        ]);
    }

    public function configurationId(): Uuid
    {
        return Uuid::fromString($this->aggregateId());
    }

    public function startNode(): Node
    {
        return Node::fromArray($this->payload['startNode']);
    }

    public function endNode(): Node
    {
        return Node::fromArray($this->payload['endNode']);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->payload['userId']);
    }
}
