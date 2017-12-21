<?php
declare(strict_types = 1);

namespace ProophExample\Aggregate;

use Prooph\EventMachine\EventMachine;
use ProophExample\Messaging\Command;
use ProophExample\Messaging\Event;

/**
 * Class CacheableUserDescription
 *
 * CacheableUserDescription illustrates an alternative way to describe aggregate behaviour. Advantage of the shown style
 * is that you can make use of EventMachine::compileCacheableConfig(). See note of UserDescription for more details.
 *
 * @package ProophExample\Aggregate
 */
final class CacheableUserDescription
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
            ->identifiedBy(self::IDENTIFIER)
            //Use callable array syntax, so that event machine config can be cached (not possible with closures)
            //A modern IDE like PHPStorm is able to resolve this reference so that it is found by usage/refactoring look ups
            ->handle([CachableUserFunction::class, 'registerUser'])
            ->recordThat(Event::USER_WAS_REGISTERED)
            ->apply([CachableUserFunction::class, 'whenUserWasRegistered'])
            ->orRecordThat(Event::USER_REGISTRATION_FAILED)
            ->apply([CachableUserFunction::class, 'whenUserRegistrationFailed']);
    }

    private static function describeChangeUsername(EventMachine $eventMachine): void
    {
        $eventMachine->process(Command::CHANGE_USERNAME)
            ->withExisting(Aggregate::USER)
            ->handle([CachableUserFunction::class, 'changeUsername'])
            ->recordThat(Event::USERNAME_WAS_CHANGED)
            ->apply([CachableUserFunction::class, 'whenUsernameWasChanged']);
    }

    private function __construct()
    {
        //static class only
    }
}
