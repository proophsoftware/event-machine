<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model;

use Prooph\EventSourcing\AggregateChanged;
use Prooph\EventSourcing\AggregateRoot;
use Prooph\Workshop\Model\Configuration\Node;
use Prooph\Workshop\Model\Event\UsernameWasChanged;
use Prooph\Workshop\Model\Event\UserWasRegistered;
use Prooph\Workshop\Model\User\UserId;
use Ramsey\Uuid\Uuid;

final class User extends AggregateRoot
{
    /**
     * @var UserId
     */
    private $userId;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $email;

    public static function register(UserId $userId, string $username, string $email): User
    {
        $self = new self();

        $self->recordThat(UserWasRegistered::occur($userId->toString(), [
            'username' => $username,
            'email' => $email
        ]));

        return $self;
    }

    public function startNewConfiguration(Uuid $configurationId, Node $startNode, Node $endNode): Configuration
    {
        return Configuration::startNewConfig($configurationId, $startNode, $endNode, $this->userId);
    }

    public function changeUsername(string $newUsername): void
    {
        $this->recordThat(UsernameWasChanged::occur($this->userId->toString(), [
            'oldName' => $this->username,
            'newName' => $newUsername
        ]));
    }

    protected function aggregateId(): string
    {
        return $this->userId->toString();
    }

    /**
     * Apply given event
     */
    protected function apply(AggregateChanged $event): void
    {
        switch ($event->messageName()) {
            case 'UserWasRegistered':
                $this->whenUserWasRegistered($event);
                break;
            case 'UsernameWasChanged':
                $this->whenUsernameWasChanged($event);
                break;
        }
    }

    protected function whenUserWasRegistered(UserWasRegistered $event): void
    {
        $this->userId = $event->userId();
        $this->username = $event->username();
        $this->email = $event->email();
    }

    protected function whenUsernameWasChanged(UsernameWasChanged $event): void
    {
        $this->username = $event->newUsername();
    }
}
