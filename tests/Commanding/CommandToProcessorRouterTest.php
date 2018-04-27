<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Commanding;

use Prooph\Common\Event\ProophActionEventEmitter;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Aggregate\ContextProvider;
use Prooph\EventMachine\Commanding\CommandProcessor;
use Prooph\EventMachine\Commanding\CommandToProcessorRouter;
use Prooph\EventMachine\Container\ContextProviderFactory;
use Prooph\EventMachineTest\BasicTestCase;
use Prooph\EventStore\EventStore;
use Prooph\ServiceBus\MessageBus;
use Prooph\SnapshotStore\SnapshotStore;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;

final class CommandToProcessorRouterTest extends BasicTestCase
{
    /**
     * @test
     */
    public function it_sets_command_processor_as_command_handler()
    {
        $commandMap = [
            'TestCommand' => [
                'commandName' => 'TestCommand',
                'aggregateType' => 'User',
                'createAggregate' => true,
                'aggregateIdentifier' => 'id',
                'aggregateFunction' => function () {
                },
                'servicesToInject' => [],
                'eventRecorderMap' => [],
                'streamName' => 'event_stream',
                'contextProvider' => 'TestContextProvider',
            ],
        ];

        $aggregateDescriptions = [
            'User' => [
                'eventApplyMap' => [
                    'UserWasRegistered' => function () {
                    },
                ],
            ],
        ];

        $container = $this->prophesize(ContainerInterface::class);
        $messageFactory = $this->prophesize(MessageFactory::class);
        $eventStore = $this->prophesize(EventStore::class);
        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $contextProvider = $this->prophesize(ContextProvider::class);
        $contextProviderFactory = $this->prophesize(ContextProviderFactory::class);
        $contextProviderFactory->build(Argument::exact('TestContextProvider'))->willReturn($contextProvider->reveal())->shouldBeCalled();

        $router = new CommandToProcessorRouter(
            $container->reveal(),
            $commandMap,
            $aggregateDescriptions,
            $messageFactory->reveal(),
            $eventStore->reveal(),
            $contextProviderFactory->reveal(),
            $snapshotStore->reveal()
        );

        $actionEvent = (new ProophActionEventEmitter())->getNewActionEvent(MessageBus::EVENT_DISPATCH);

        $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_NAME, 'TestCommand');

        $router->onRouteMessage($actionEvent);

        /** @var CommandProcessor $commandProcessor */
        $commandProcessor = $actionEvent->getParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER);

        self::assertInstanceOf(CommandProcessor::class, $commandProcessor);
    }
}
