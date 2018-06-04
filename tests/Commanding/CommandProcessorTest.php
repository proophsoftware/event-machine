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

use Prooph\EventMachine\Aggregate\ContextProvider;
use Prooph\EventMachine\Commanding\CommandProcessor;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Messaging\GenericJsonSchemaEvent;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachineTest\Aggregate\Stub\ContextAwareAggregateDescription;
use Prooph\EventMachineTest\BasicTestCase;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\StreamName;
use ProophExample\Aggregate\Aggregate;
use ProophExample\Aggregate\CacheableUserDescription;
use ProophExample\Aggregate\UserDescription;
use ProophExample\Messaging\Command;
use ProophExample\Messaging\Event;
use ProophExample\Messaging\MessageDescription;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;

final class CommandProcessorTest extends BasicTestCase
{
    /**
     * @test
     */
    public function it_processes_command_that_creates_new_aggregate()
    {
        $eventMachine = new EventMachine();

        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(CacheableUserDescription::class);

        $container = $this->prophesize(ContainerInterface::class);

        $eventMachine->initialize($container->reveal());

        $config = $eventMachine->compileCacheableConfig();

        $commandRouting = $config['compiledCommandRouting'];
        $aggregateDescriptions = $config['aggregateDescriptions'];

        $recordedEvents = [];

        $eventStore = $this->prophesize(EventStore::class);

        $eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = iterator_to_array($args[1]);
        });

        $processorDesc = $commandRouting[Command::REGISTER_USER];
        $processorDesc['eventApplyMap'] = $aggregateDescriptions[Aggregate::USER]['eventApplyMap'];

        $commandProcessor = CommandProcessor::fromDescriptionArrayAndDependencies(
            $processorDesc,
            $this->getMockedEventMessageFactory(),
            $eventStore->reveal()
        );

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->getMockedCommandMessageFactory()->createMessageFromArray(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
        ]);

        $commandProcessor($registerUser);

        self::assertCount(1, $recordedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        self::assertEquals(Event::USER_WAS_REGISTERED, $event->messageName());
        self::assertEquals([
            '_causation_id' => $registerUser->uuid()->toString(),
            '_causation_name' => $registerUser->messageName(),
            '_aggregate_version' => 1,
            '_aggregate_id' => $userId,
            '_aggregate_type' => 'User',
        ], $event->metadata());
    }

    /**
     * @test
     */
    public function it_processes_command_with_existing_aggregate()
    {
        $eventMachine = new EventMachine();

        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(CacheableUserDescription::class);

        $container = $this->prophesize(ContainerInterface::class);

        $eventMachine->initialize($container->reveal());

        $config = $eventMachine->compileCacheableConfig();

        $commandRouting = $config['compiledCommandRouting'];
        $aggregateDescriptions = $config['aggregateDescriptions'];

        $userId = Uuid::uuid4()->toString();

        $recordedEvents = [];

        $eventStore = $this->prophesize(EventStore::class);

        $eventFactory = $this->getMockedEventMessageFactory();

        $eventStore->load(new StreamName('event_stream'), 1, null, Argument::type(MetadataMatcher::class))
            ->will(function ($args) use ($userId, $eventFactory) {
                $event = $eventFactory->createMessageFromArray('UserWasRegistered', [
                    'payload' => [
                        UserDescription::IDENTIFIER => $userId,
                        UserDescription::USERNAME => 'Alex',
                        UserDescription::EMAIL => 'contact@prooph.de',
                    ],
                    'metadata' => [
                        '_causation_id' => Uuid::uuid4()->toString(),
                        '_causation_name' => 'RegisterUser',
                        '_aggregate_version' => 1,
                        '_aggregate_id' => $userId,
                        '_aggregate_type' => 'User',
                    ],
                ]);

                return new \ArrayIterator([$event]);
            });

        $eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = iterator_to_array($args[1]);
        });

        $processorDesc = $commandRouting[Command::CHANGE_USERNAME];
        $processorDesc['eventApplyMap'] = $aggregateDescriptions[Aggregate::USER]['eventApplyMap'];

        $commandProcessor = CommandProcessor::fromDescriptionArrayAndDependencies(
            $processorDesc,
            $this->getMockedEventMessageFactory(),
            $eventStore->reveal()
        );

        $changeUsername = $this->getMockedCommandMessageFactory()->createMessageFromArray(Command::CHANGE_USERNAME, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Max',
        ]);

        $commandProcessor($changeUsername);

        self::assertCount(1, $recordedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        self::assertEquals(Event::USERNAME_WAS_CHANGED, $event->messageName());
        self::assertEquals([
            '_causation_id' => $changeUsername->uuid()->toString(),
            '_causation_name' => $changeUsername->messageName(),
            '_aggregate_version' => 2,
            '_aggregate_id' => $userId,
            '_aggregate_type' => 'User',
        ], $event->metadata());
    }

    /**
     * @test
     */
    public function it_prcoesses_alternative_event_recording()
    {
        $eventMachine = new EventMachine();

        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(CacheableUserDescription::class);

        $container = $this->prophesize(ContainerInterface::class);

        $eventMachine->initialize($container->reveal());

        $config = $eventMachine->compileCacheableConfig();

        $commandRouting = $config['compiledCommandRouting'];
        $aggregateDescriptions = $config['aggregateDescriptions'];

        $recordedEvents = [];

        $eventStore = $this->prophesize(EventStore::class);

        $eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = iterator_to_array($args[1]);
        });

        $processorDesc = $commandRouting[Command::REGISTER_USER];
        $processorDesc['eventApplyMap'] = $aggregateDescriptions[Aggregate::USER]['eventApplyMap'];

        $commandProcessor = CommandProcessor::fromDescriptionArrayAndDependencies(
            $processorDesc,
            $this->getMockedEventMessageFactory(),
            $eventStore->reveal()
        );

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->getMockedCommandMessageFactory()->createMessageFromArray(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
            //Force failing of user registration
            'shouldFail' => true,
        ]);

        $commandProcessor($registerUser);

        self::assertCount(1, $recordedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        self::assertEquals(Event::USER_REGISTRATION_FAILED, $event->messageName());
        self::assertEquals([
            '_causation_id' => $registerUser->uuid()->toString(),
            '_causation_name' => $registerUser->messageName(),
            '_aggregate_version' => 1,
            '_aggregate_id' => $userId,
            '_aggregate_type' => 'User',
        ], $event->metadata());
    }

    /**
     * @test
     */
    public function it_does_nothing_if_aggregate_function_yields_null_to_indicate_that_no_event_should_be_recorded()
    {
        $eventMachine = new EventMachine();

        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(CacheableUserDescription::class);

        $container = $this->prophesize(ContainerInterface::class);

        $eventMachine->initialize($container->reveal());

        $config = $eventMachine->compileCacheableConfig();

        $commandRouting = $config['compiledCommandRouting'];
        $aggregateDescriptions = $config['aggregateDescriptions'];

        $userId = Uuid::uuid4()->toString();

        $recordedEvents = [];

        $eventStore = $this->prophesize(EventStore::class);

        $eventFactory = $this->getMockedEventMessageFactory();

        $eventStore->load(new StreamName('event_stream'), 1, null, Argument::type(MetadataMatcher::class))
            ->will(function ($args) use ($userId, $eventFactory) {
                $event = $eventFactory->createMessageFromArray('UserWasRegistered', [
                    'payload' => [
                        UserDescription::IDENTIFIER => $userId,
                        UserDescription::USERNAME => 'Alex',
                        UserDescription::EMAIL => 'contact@prooph.de',
                    ],
                    'metadata' => [
                        '_causation_id' => Uuid::uuid4()->toString(),
                        '_causation_name' => 'RegisterUser',
                        '_aggregate_version' => 1,
                        '_aggregate_id' => $userId,
                        '_aggregate_type' => 'User',
                    ],
                ]);

                return new \ArrayIterator([$event]);
            });

        $eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = iterator_to_array($args[1]);
        });

        $processorDesc = $commandRouting[Command::DO_NOTHING];
        $processorDesc['eventApplyMap'] = $aggregateDescriptions[Aggregate::USER]['eventApplyMap'];

        $commandProcessor = CommandProcessor::fromDescriptionArrayAndDependencies(
            $processorDesc,
            $this->getMockedEventMessageFactory(),
            $eventStore->reveal()
        );

        $doNothing = $this->getMockedCommandMessageFactory()->createMessageFromArray(Command::DO_NOTHING, [
            UserDescription::IDENTIFIER => $userId,
        ]);

        $commandProcessor($doNothing);

        self::assertCount(0, $recordedEvents);
    }

    /**
     * @test
     */
    public function it_provides_context_using_context_provider()
    {
        $eventMachine = new EventMachine();

        $eventMachine->load(ContextAwareAggregateDescription::class);

        $container = $this->prophesize(ContainerInterface::class);

        $eventMachine->initialize($container->reveal());

        $config = $eventMachine->compileCacheableConfig();

        $commandRouting = $config['compiledCommandRouting'];
        $aggregateDescriptions = $config['aggregateDescriptions'];

        $recordedEvents = [];

        $eventStore = $this->prophesize(EventStore::class);

        $eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = iterator_to_array($args[1]);
        });

        $processorDesc = $commandRouting['AddCAA'];
        $processorDesc['eventApplyMap'] = $aggregateDescriptions['CAA']['eventApplyMap'];

        $contextProvider = null;

        if ($processorDesc['contextProvider'] ?? null && $processorDesc['contextProvider'] === 'MsgContextProvider') {
            $contextProvider = $this->prophesize(ContextProvider::class);
            $contextProvider->provide(Argument::type(Message::class))->willReturn(['msg' => 'it works']);
            $contextProvider = $contextProvider->reveal();
        }

        $commandProcessor = CommandProcessor::fromDescriptionArrayAndDependencies(
            $processorDesc,
            $this->getMockedEventMessageFactory(),
            $eventStore->reveal(),
            null,
            $contextProvider
        );

        $addCAA = $this->getMockedCommandMessageFactory()->createMessageFromArray('AddCAA', ['id' => 1]);

        $commandProcessor($addCAA);

        self::assertCount(1, $recordedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        self::assertEquals('CAAAdded', $event->messageName());
        self::assertEquals([
            'id' => 1,
            'context' => ['msg' => 'it works'],
        ], $event->payload());
    }

    /**
     * @test
     */
    public function it_adds_additional_metadata_to_event()
    {
        $eventMachine = new EventMachine();

        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(CacheableUserDescription::class);

        $container = $this->prophesize(ContainerInterface::class);

        $eventMachine->initialize($container->reveal());

        $config = $eventMachine->compileCacheableConfig();

        $commandRouting = $config['compiledCommandRouting'];
        $aggregateDescriptions = $config['aggregateDescriptions'];

        $recordedEvents = [];

        $eventStore = $this->prophesize(EventStore::class);

        $eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = iterator_to_array($args[1]);
        });

        $processorDesc = $commandRouting[Command::REGISTER_USER];
        $processorDesc['eventApplyMap'] = $aggregateDescriptions[Aggregate::USER]['eventApplyMap'];

        $arFunc = $processorDesc['aggregateFunction'];

        //Wrap ar function to add additional metadata for this test
        $processorDesc['aggregateFunction'] = function (Message $registerUser) use ($arFunc): \Generator {
            [$event] = iterator_to_array($arFunc($registerUser));
            [$eventName, $payload] = $event;
            yield [$eventName, $payload, ['additional' => 'metadata']];
        };

        $commandProcessor = CommandProcessor::fromDescriptionArrayAndDependencies(
            $processorDesc,
            $this->getMockedEventMessageFactory(),
            $eventStore->reveal()
        );

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->getMockedCommandMessageFactory()->createMessageFromArray(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
        ]);

        $commandProcessor($registerUser);

        self::assertCount(1, $recordedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        self::assertEquals(Event::USER_WAS_REGISTERED, $event->messageName());
        self::assertEquals([
            '_causation_id' => $registerUser->uuid()->toString(),
            '_causation_name' => $registerUser->messageName(),
            '_aggregate_version' => 1,
            '_aggregate_id' => $userId,
            '_aggregate_type' => 'User',
            'additional' => 'metadata',
        ], $event->metadata());
    }
}
