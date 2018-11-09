<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\CustomMessages\Aggregate;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use ProophExample\CustomMessages\Api\Command;
use ProophExample\CustomMessages\Api\Event;
use ProophExample\CustomMessages\Command\ChangeUsername;
use ProophExample\CustomMessages\Command\RegisterUser;
use ProophExample\CustomMessages\Event\UsernameChanged;
use ProophExample\CustomMessages\Event\UserRegistered;

/**
 * Class UserDescription
 *
 * Tell EventMachine how to handle commands with aggregates, which events are yielded by the handle methods
 * and how to apply the yielded events to the aggregate state.
 *
 * Please note:
 * UserDescription uses closures. It is the fastest and most readable way of describing
 * aggregate behaviour BUT closures cannot be serialized/cached.
 * So the closure style is useful for learning and prototyping but if you want to use Event Machine for
 * production, you should consider using a cacheable description like illustrated with CacheableUserDescription.
 * Also see EventMachine::cacheableConfig() which throws an exception if it detects usage of closure
 * The returned array can be used to call EventMachine::fromCachedConfig(). You can json_encode the config and store it
 * in a json file.
 *
 * @package ProophExample\Aggregate
 */
final class UserDescription implements EventMachineDescription
{
    public const IDENTIFIER = 'userId';
    public const USERNAME = 'username';
    public const EMAIL = 'email';

    const STATE_CLASS = UserState::class;

    public static function describe(EventMachine $eventMachine): void
    {
        self::describeRegisterUser($eventMachine);
        self::describeChangeUsername($eventMachine);
    }

    private static function describeRegisterUser(EventMachine $eventMachine): void
    {
        $eventMachine->process(Command::REGISTER_USER)
            ->withNew(Aggregate::USER)
            ->identifiedBy(self::IDENTIFIER)
            // Note: Our custom command is passed to the function
            ->handle(function (RegisterUser $registerUser) {
                //We can return a custom event
                yield new UserRegistered((array) $registerUser);
            })
            ->recordThat(Event::USER_WAS_REGISTERED)
            // The custom event is passed to the apply function
            ->apply(function (UserRegistered $event) {
                return new UserState((array) $event);
            });
    }

    private static function describeChangeUsername(EventMachine $eventMachine): void
    {
        $eventMachine->process(Command::CHANGE_USERNAME)
            ->withExisting(Aggregate::USER)
            // This time we handle command with existing aggregate, hence we get current user state injected
            ->handle(function (UserState $user, ChangeUsername $changeUsername) {
                yield new UsernameChanged([
                    self::IDENTIFIER => $user->userId,
                    'oldName' => $user->username,
                    'newName' => $changeUsername->username,
                ]);
            })
            ->recordThat(Event::USERNAME_WAS_CHANGED)
            // Same here, UsernameChanged is NOT the first event, so current user state is passed
            ->apply(function (UserState $user, UsernameChanged $event) {
                $user->username = $event->newName;

                return $user;
            });
    }

    private function __construct()
    {
        //static class only
    }
}
