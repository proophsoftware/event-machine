<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\OopFlavour\Aggregate;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\Runtime\Oop\FlavourHint;
use ProophExample\FunctionalFlavour\Api\Command;
use ProophExample\FunctionalFlavour\Api\Event;

/**
 * Class UserDescription
 *
 * @package ProophExample\Aggregate
 */
final class UserDescription implements EventMachineDescription
{
    public const IDENTIFIER = 'userId';
    public const USERNAME = 'username';
    public const EMAIL = 'email';

    public static function describe(EventMachine $eventMachine): void
    {
        self::describeRegisterUser($eventMachine);
        self::describeChangeUsername($eventMachine);
    }

    private static function describeRegisterUser(EventMachine $eventMachine): void
    {
        $eventMachine->process(Command::REGISTER_USER)
            ->withNew(User::TYPE)
            ->identifiedBy(self::IDENTIFIER)
            // Note: Our custom command is passed to the function
            ->handle([User::class, 'register'])
            ->recordThat(Event::USER_WAS_REGISTERED)
            // We pass a call hint. This is a No-Op callable
            // because OOPAggregateCallInterceptor does not use this callable
            // see OOPAggregateCallInterceptor::callApplyFirstEvent()
            // and OOPAggregateCallInterceptor::callApplySubsequentEvent()
            ->apply([FlavourHint::class, 'useAggregate']);
    }

    private static function describeChangeUsername(EventMachine $eventMachine): void
    {
        $eventMachine->process(Command::CHANGE_USERNAME)
            ->withExisting(User::TYPE)
            ->handle([FlavourHint::class, 'useAggregate'])
            ->recordThat(Event::USERNAME_WAS_CHANGED)
            ->apply([FlavourHint::class, 'useAggregate']);
    }

    private function __construct()
    {
        //static class only
    }
}
