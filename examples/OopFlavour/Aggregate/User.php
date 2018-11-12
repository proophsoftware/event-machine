<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\OopFlavour\Aggregate;

use Prooph\EventMachine\Exception\RuntimeException;
use ProophExample\FunctionalFlavour\Command\ChangeUsername;
use ProophExample\FunctionalFlavour\Command\RegisterUser;
use ProophExample\FunctionalFlavour\Event\UsernameChanged;
use ProophExample\FunctionalFlavour\Event\UserRegistered;

final class User
{
    public const TYPE = 'User';

    private $userId;

    private $username;

    private $email;

    private $recordedEvents = [];

    public static function reconstituteFromHistory(iterable $history): self
    {
        $self = new self();
        foreach ($history as $event) {
            $self->apply($event);
        }

        return $self;
    }

    public static function register(RegisterUser $command): self
    {
        $self = new self();

        $self->recordThat(new UserRegistered([
            'userId' => $command->userId,
            'username' => $command->username,
            'email' => $command->email,
        ]));

        return $self;
    }

    public function changeName(ChangeUsername $command): void
    {
        $this->recordThat(new UsernameChanged([
            'userId' => $this->userId,
            'oldName' => $this->username,
            'newName' => $command->username,
        ]));
    }

    public function popRecordedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    public function apply($event): void
    {
        switch (\get_class($event)) {
            case UserRegistered::class:
                /** @var UserRegistered $event */
                $this->userId = $event->userId;
                $this->username = $event->username;
                $this->email = $event->email;
                break;
            case UsernameChanged::class:
                /** @var UsernameChanged $event */
                $this->username = $event->newName;
                break;
            default:
                throw new RuntimeException('Unknown event: ' . \get_class($event));
        }
    }

    public function toArray(): array
    {
        return (array) $this;
    }

    private function recordThat($event): void
    {
        $this->recordedEvents[] = $event;
    }

    private function __construct()
    {
    }
}
