<?php
declare(strict_types = 1);

namespace ProophExample\Aggregate;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use ProophExample\Messaging\Command;
use ProophExample\Messaging\Event;

/**
 * Class UserDescription
 *
 * Tell EventMachine how to handle commands with aggregates, which events are yielded by the handle methods
 * and how to apply the yielded events to the aggregate state.
 *
 * @package ProophExample\Aggregate
 */
final class UserDescription implements EventMachineDescription
{
    const IDENTIFIER = 'userId';
    const USERNAME = 'username';
    const EMAIL = 'email';

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
            //Every command for that aggregate SHOULD include the identifier property specified here
            //If not called, identifier defaults to "id"
            ->identifiedBy(self::IDENTIFIER)
            //If command is handled with a new aggregate no state is passed only the command
            ->handle(function(array $registerUser) {
                //We just turn the command payload into event payload by yielding it
                yield $registerUser;
            })
            ->recordThat(Event::USER_WAS_REGISTERED)
            //Apply callback of the first recorded event don't get aggregate state injected
            //what you return in an apply method will be passed to the next pair of handle & apply methods as aggregate state
            //you can use anything for aggregate state - we use a simple class with public properties
            ->apply(function (array $userWasRegistered) {
                $user = new UserState();
                $user->id = $userWasRegistered[self::IDENTIFIER];
                $user->username = $userWasRegistered['username'];
                $user->email = $userWasRegistered['email'];
                return $user;
            });
    }

    private static function describeChangeUsername(EventMachine $eventMachine): void
    {
        $eventMachine->process(Command::CHANGE_USERNAME)
            ->withExisting(Aggregate::USER)
            ->handle(function (UserState $user, array $changeUsername) {
                yield [
                    self::IDENTIFIER => $user->id,
                    'oldName' => $user->username,
                    'newName' => $changeUsername['username']
                ];
            })
            ->recordThat(Event::USERNAME_WAS_CHANGED)
            ->apply(function (UserState $user, array $usernameWasChanged) {
                $user->username = $usernameWasChanged['newName'];
                return $user;
            });
    }

    private function __construct()
    {
        //static class only
    }
}
