<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Messaging\MessageDispatcher;
use Prooph\EventMachine\Persistence\DocumentStore;
use Prooph\EventMachine\Runtime\Flavour;
use Prooph\EventMachine\Runtime\FunctionalFlavour;
use Prooph\EventMachine\Runtime\OopFlavour;
use ProophExample\FunctionalFlavour\Api\MessageDescription;
use ProophExample\FunctionalFlavour\ExampleFunctionalPort;
use ProophExample\FunctionalFlavour\ProcessManager\SendWelcomeEmail;
use ProophExample\FunctionalFlavour\Projector\RegisteredUsersProjector;
use ProophExample\FunctionalFlavour\Resolver\GetUserResolver;
use ProophExample\FunctionalFlavour\Resolver\GetUsersResolver;
use ProophExample\OopFlavour\Aggregate\User;
use ProophExample\OopFlavour\Aggregate\UserDescription;
use ProophExample\OopFlavour\ExampleOopPort;

class EventMachineOopFlavourTest extends EventMachineTestAbstract
{
    protected function loadEventMachineDescriptions(EventMachine $eventMachine)
    {
        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(UserDescription::class);
    }

    protected function getFlavour(): Flavour
    {
        return new OopFlavour(
            new ExampleOopPort(),
            new FunctionalFlavour(new ExampleFunctionalPort())
        );
    }

    protected function getRegisteredUsersProjector(DocumentStore $documentStore)
    {
        return new RegisteredUsersProjector($documentStore);
    }

    protected function getUserRegisteredListener(MessageDispatcher $messageDispatcher)
    {
        return new SendWelcomeEmail($messageDispatcher);
    }

    protected function getUserResolver(array $cachedUserState): callable
    {
        return new GetUserResolver($cachedUserState);
    }

    protected function getUsersResolver(array $cachedUsers): callable
    {
        return new GetUsersResolver($cachedUsers);
    }

    protected function assertLoadedUserState($userState): void
    {
        self::assertInstanceOf(User::class, $userState);
        self::assertEquals('Tester', $userState->toArray()['username']);
    }
}
