<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest;

use Prooph\Common\Messaging\Message as ProophMessage;
use Prooph\EventMachine\Commanding\CommandPreProcessor;
use Prooph\EventMachine\Container\EventMachineContainer;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Messaging\MessageDispatcher;
use Prooph\EventMachine\Persistence\DocumentStore;
use Prooph\EventMachine\Runtime\Flavour;
use Prooph\EventMachine\Runtime\PrototypingFlavour;
use ProophExample\PrototypingFlavour\Aggregate\UserDescription;
use ProophExample\PrototypingFlavour\Aggregate\UserState;
use ProophExample\PrototypingFlavour\Messaging\CommandWithCustomHandler;
use ProophExample\PrototypingFlavour\Messaging\MessageDescription;
use ProophExample\PrototypingFlavour\ProcessManager\SendWelcomeEmail;
use ProophExample\PrototypingFlavour\Projector\RegisteredUsersProjector;
use ProophExample\PrototypingFlavour\Resolver\GetUserResolver;
use ProophExample\PrototypingFlavour\Resolver\GetUsersResolver;
use Psr\Container\ContainerInterface;

class EventMachinePrototypingFlavourTest extends EventMachineTestAbstract
{
    protected function loadEventMachineDescriptions(EventMachine $eventMachine)
    {
        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(UserDescription::class);
    }

    protected function getFlavour(): Flavour
    {
        return new PrototypingFlavour();
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
        self::assertInstanceOf(UserState::class, $userState);
        self::assertEquals('Tester', $userState->username);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_config_should_be_cached_but_contains_closures()
    {
        $eventMachine = new EventMachine();

        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(UserDescription::class);

        $container = $this->prophesize(ContainerInterface::class);

        $eventMachine->initialize($container->reveal());

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('At least one EventMachineDescription contains a Closure and is therefor not cacheable!');

        $eventMachine->compileCacheableConfig();
    }

    /**
     * @test
     */
    public function it_stops_dispatch_if_preprocessor_sets_metadata_flag()
    {
        $eventMachine = new EventMachine();

        $eventMachine->load(CommandWithCustomHandler::class);

        $noOpHandler = new class() implements CommandPreProcessor {
            private $msg;

            /**
             * {@inheritdoc}
             */
            public function preProcess(ProophMessage $message): ProophMessage
            {
                if ($message instanceof Message) {
                    $this->msg = $message->get('msg');
                }

                return $message->withAddedMetadata(EventMachine::CMD_METADATA_STOP_DISPATCH, true);
            }

            public function msg(): ?string
            {
                return $this->msg;
            }
        };

        $eventMachine->initialize(new EventMachineContainer($eventMachine));

        $eventMachine->bootstrapInTestMode([], [
            CommandWithCustomHandler::NO_OP_HANDLER => $noOpHandler,
        ]);

        $eventMachine->dispatch(CommandWithCustomHandler::CMD_DO_NOTHING, [
            'msg' => 'test',
        ]);

        $this->assertEquals('test', $noOpHandler->msg());
    }
}
