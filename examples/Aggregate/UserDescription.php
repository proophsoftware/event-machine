<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\Aggregate;

use Prooph\Common\Messaging\Message;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use ProophExample\Messaging\Command;
use ProophExample\Messaging\Event;

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
    const IDENTIFIER = 'userId';
    const USERNAME = 'username';
    const EMAIL = 'email';
    const DATA_FROM_EXTERNAL_SERVICE = 'dataFromExternalService';

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
            // Every command for that aggregate SHOULD include the identifier property specified here
            // If not called, identifier defaults to "id"
            ->identifiedBy(self::IDENTIFIER)
            // If command is handled with a new aggregate no state is passed only the command
            ->handle(function (Message $registerUser) {
                //We just turn the command payload into event payload by yielding an event tuple
                yield [Event::USER_WAS_REGISTERED, $registerUser->payload()];
            })
            ->recordThat(Event::USER_WAS_REGISTERED)
            // Apply callback of the first recorded event don't get aggregate state injected
            // what you return in an apply method will be passed to the next pair of handle & apply methods as aggregate state
            // you can use anything for aggregate state - we use a simple class with public properties
            ->apply(function (Message $userWasRegistered) {
                $user = new UserState();
                $user->id = $userWasRegistered->payload()[self::IDENTIFIER];
                $user->username = $userWasRegistered->payload()['username'];
                $user->email = $userWasRegistered->payload()['email'];

                return $user;
            });
    }

    private static function describeChangeUsername(EventMachine $eventMachine): void
    {
        $eventMachine->process(Command::CHANGE_USERNAME)
            ->withExisting(Aggregate::USER)
            // This time we handle command with existing aggregate, hence we get current user state injected
            ->handle(function (UserState $user, Message $changeUsername) {
                yield [Event::USERNAME_WAS_CHANGED, [
                    self::IDENTIFIER => $user->id,
                    'oldName' => $user->username,
                    'newName' => $changeUsername->payload()['username'],
                ]];
            })
            ->recordThat(Event::USERNAME_WAS_CHANGED)
            // Same here, UsernameWasChanged is NOT the first event, so current user state is injected
            ->apply(function (UserState $user, Message $usernameWasChanged) {
                $user->username = $usernameWasChanged->payload()['newName'];

                return $user;
            });
    }

    private function __construct()
    {
        //static class only
    }
}
