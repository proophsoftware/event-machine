<?php
declare(strict_types = 1);

namespace Prooph\EventMachineTest;

use Prooph\Common\Event\ProophActionEventEmitter;
use Prooph\Common\Messaging\Message;
use Prooph\EventMachine\Container\ContainerChain;
use Prooph\EventMachine\Container\EventMachineContainer;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventStore\ActionEventEmitterEventStore;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalActionEventEmitterEventStore;
use Prooph\EventStore\TransactionalEventStore;
use Prooph\ServiceBus\Async\MessageProducer;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use ProophExample\Aggregate\Aggregate;
use ProophExample\Aggregate\CacheableUserDescription;
use ProophExample\Aggregate\UserDescription;
use ProophExample\Aggregate\UserState;
use ProophExample\Messaging\Command;
use ProophExample\Messaging\Event;
use ProophExample\Messaging\MessageDescription;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;

class EventMachineTest extends BasicTestCase
{
    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var ActionEventEmitterEventStore
     */
    private $actionEventEmitterEventStore;

    /**
     * @var CommandBus
     */
    private $commandBus;

    /**
     * @var EventBus
     */
    private $eventBus;
    /**
     * @var ContainerInterface
     */
    private $appContainer;
    /**
     * @var EventMachine
     */
    private $eventMachine;

    /**
     * @var ContainerChain
     */
    private $containerChain;

    protected function setUp()
    {
        $this->eventMachine = new EventMachine();

        $this->eventMachine->load(MessageDescription::class);
        $this->eventMachine->load(CacheableUserDescription::class);

        $this->eventStore = $this->prophesize(EventStore::class);

        $this->actionEventEmitterEventStore = new ActionEventEmitterEventStore(
            $this->eventStore->reveal(),
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );
        $this->commandBus = new CommandBus();
        $this->eventBus = new EventBus();

        $this->appContainer = $this->prophesize(ContainerInterface::class);

        $self = $this;
        $this->appContainer->has(EventMachine::SERVICE_ID_EVENT_STORE)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_EVENT_STORE)->will(function ($args) use ($self) {
            return $self->actionEventEmitterEventStore;
        });

        $this->appContainer->has(EventMachine::SERVICE_ID_COMMAND_BUS)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_COMMAND_BUS)->will(function ($args) use ($self) {
            return $self->commandBus;
        });

        $this->appContainer->has(EventMachine::SERVICE_ID_EVENT_BUS)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_EVENT_BUS)->will(function ($args) use ($self) {
            return $self->eventBus;
        });

        $this->appContainer->has(EventMachine::SERVICE_ID_MESSAGE_FACTORY)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_JSON_SCHEMA_ASSERTION)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_SNAPSHOT_STORE)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_ASYNC_EVENT_PRODUCER)->willReturn(false);

        $this->containerChain = new ContainerChain(
            $this->appContainer->reveal(),
            new EventMachineContainer($this->eventMachine)
        );

    }

    protected function tearDown()
    {
        $this->eventMachine = null;
        $this->eventStore = null;
        $this->commandBus = null;
        $this->appContainer = null;
    }

    /**
     * @test
     */
    public function it_dispatches_a_known_command()
    {
        $recordedEvents = [];

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = iterator_to_array($args[1]);
        });

        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function (Message $event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->eventMachine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventMachine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $this->eventMachine->bootstrap()->dispatch($registerUser);

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
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

        self::assertSame($event, $publishedEvents[0]);
    }

    /**
     * @test
     */
    public function it_creates_message_on_dispatch_if_only_name_and_payload_is_given()
    {
        $recordedEvents = [];

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = iterator_to_array($args[1]);
        });

        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function (Message $event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->eventMachine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $this->eventMachine->bootstrap()->dispatch(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
        ]);

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        self::assertEquals(Event::USER_WAS_REGISTERED, $event->messageName());
        self::assertSame($event, $publishedEvents[0]);
    }

    /**
     * @test
     */
    public function it_enables_async_switch_message_router_if_container_has_a_producer()
    {
        $producedEvents = [];

        $eventMachine = $this->eventMachine;

        $messageProducer = $this->prophesize(MessageProducer::class);
        $messageProducer->__invoke(Argument::type(Message::class))
            ->will(function ($args) use (&$producedEvents, $eventMachine) {
                $producedEvents[] = $args[0];
                $eventMachine->dispatch($args[0]);
            });

        $this->appContainer->has(EventMachine::SERVICE_ID_ASYNC_EVENT_PRODUCER)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_ASYNC_EVENT_PRODUCER)->will(function ($args) use ($messageProducer) {
            return $messageProducer->reveal();
        });

        $recordedEvents = [];

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = iterator_to_array($args[1]);
        });

        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function (Message $event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->eventMachine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventMachine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $this->eventMachine->bootstrap()->dispatch($registerUser);

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        self::assertCount(1, $producedEvents);
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

        //Event should have modified metadata (async switch) and therefor be another instance (as it is immutable)
        self::assertNotSame($event, $publishedEvents[0]);
        self::assertTrue($publishedEvents[0]->metadata()['handled-async']);
        self::assertEquals(Event::USER_WAS_REGISTERED, $publishedEvents[0]->messageName());
        self::assertSame($publishedEvents[0], $producedEvents[0]);
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
    public function it_can_load_aggregate_state()
    {
        $this->eventMachine->initialize($this->containerChain);
        $eventMachine = $this->eventMachine;
        $userId = Uuid::uuid4()->toString();

        $this->eventStore->load(new StreamName('event_stream'), 1, null, Argument::any())->will(function ($args) use ($userId, $eventMachine) {
                return new \ArrayIterator([
                    $eventMachine->messageFactory()->createMessageFromArray(Event::USER_WAS_REGISTERED, [
                        'payload' => [
                            'userId' => $userId,
                            'username' => 'Tester',
                            'email' => 'tester@test.com'
                        ],
                        'metadata' => [
                            '_aggregate_id' => $userId,
                            '_aggregate_type' => Aggregate::USER,
                            '_aggregate_version' => 1
                        ]
                    ])
                ]);
        });

        /** @var UserState $userState */
        $userState = $eventMachine->bootstrap()->loadAggregateState(Aggregate::USER, $userId);

        self::assertInstanceOf(UserState::class, $userState);
        self::assertEquals('Tester', $userState->username);
    }

    /**
     * @test
     */
    public function it_sets_up_transaction_manager_if_event_store_supports_transactions()
    {
        $this->eventStore = $this->prophesize(TransactionalEventStore::class);

        $this->actionEventEmitterEventStore = new TransactionalActionEventEmitterEventStore(
            $this->eventStore->reveal(),
            new ProophActionEventEmitter(TransactionalActionEventEmitterEventStore::ALL_EVENTS)
        );

        $recordedEvents = [];

        $this->eventStore->beginTransaction()->shouldBeCalled();

        $this->eventStore->inTransaction()->willReturn(true);

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = iterator_to_array($args[1]);
        });

        $this->eventStore->commit()->shouldBeCalled();

        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function (Message $event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->eventMachine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $this->eventMachine->bootstrap()->dispatch(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
        ]);

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        self::assertEquals(Event::USER_WAS_REGISTERED, $event->messageName());
        self::assertSame($event, $publishedEvents[0]);
    }

    /**
     * @test
     */
    public function it_provides_message_schemas()
    {
        $this->eventMachine->initialize($this->containerChain);

        $userId = [
            'type' => 'string',
            'minLength' => 36
        ];

        $username = [
            'type' => 'string',
            'minLength' => 1
        ];

        $userDataSchema = JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
            UserDescription::EMAIL => [
                'type' => 'string',
                'format' => 'email'
            ]
        ], [
            'shouldFail' => [
                'type' => 'boolean',
            ]
        ]);

        self::assertEquals([
            'commands' => [
                Command::REGISTER_USER => $userDataSchema,
                Command::CHANGE_USERNAME => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                    UserDescription::USERNAME => $username
                ]),
            ],
            'events' => [
                Event::USER_WAS_REGISTERED => $userDataSchema,
                Event::USERNAME_WAS_CHANGED => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                    'oldName' => $username,
                    'newName' => $username,
                ]),
                Event::USER_REGISTRATION_FAILED => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                ])
            ]
            ],
            $this->eventMachine->messageSchemas()
        );
    }
}
