<?php
declare(strict_types = 1);

namespace Prooph\Workshop\ReadModel;


use Prooph\Workshop\Infrastructure\MongoDb\MongoConnection;
use Prooph\Workshop\Model\Event\DomainEvent;
use Prooph\Workshop\Model\Event\UsernameWasChanged;
use Prooph\Workshop\Model\Event\UserWasRegistered;

final class UserProjector
{
    const COLLECTION = 'user_read';
    /**
     * @var MongoConnection
     */
    private $mongoConnection;

    public function __construct(MongoConnection $connection)
    {
        $this->mongoConnection = $connection;
    }

    function __invoke(DomainEvent $event)
    {
        switch ($event->messageName()) {
            case 'UserWasRegistered':
                $this->onUserWasRegistered($event);
                break;
            case 'UsernameWasChanged':
                $this->onUsernameWasChanged($event);
                break;
        }
    }

    public function onUserWasRegistered(UserWasRegistered $event): void
    {
        $this->mongoConnection->selectCollection(self::COLLECTION)
            ->insertOne([
                '_id' => $event->userId()->toString(),
                'username' => $event->username(),
                'email' => $event->email()
            ]);
    }

    public function onUsernameWasChanged(UsernameWasChanged $event): void
    {
        $this->mongoConnection->selectCollection(self::COLLECTION)
            ->updateOne([
                '_id' => $event->userId()->toString()
            ], [
                '$set' => [
                    'username' => $event->newUsername()
                ]
            ]);
    }
}
