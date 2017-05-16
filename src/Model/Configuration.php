<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model;


use Prooph\EventSourcing\AggregateChanged;
use Prooph\EventSourcing\AggregateRoot;
use Prooph\Workshop\Model\Configuration\Node;
use Prooph\Workshop\Model\Event\NewConfigWasStarted;
use Prooph\Workshop\Model\User\UserId;
use Ramsey\Uuid\Uuid;

final class Configuration extends AggregateRoot
{
    /**
     * @var Uuid
     */
    private $configurationId;

    public static function startNewConfig(Uuid $configurationId, Node $startNode, Node $endNode, UserId $userId)
    {
        if(!$startNode->isStartNode()) {
            throw new \RuntimeException("Node ist not a start node");
        }

        if(!$endNode->isEndNode()) {
            throw new \RuntimeException("Node is not an end node");
        }

        $self = new self();

        $self->recordThat(NewConfigWasStarted::withNodesForUser($startNode, $endNode, $configurationId, $userId));

        return $self;
    }



    protected function aggregateId(): string
    {
        return $this->configurationId->toString();
    }

    /**
     * Apply given event
     */
    protected function apply(AggregateChanged $event): void
    {
        switch ($event->messageName()) {
            case 'NewConfigWasStarted':
                $this->configurationId = $event->configurationId();
                break;
        }
    }
}
