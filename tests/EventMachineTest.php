<?php
declare(strict_types = 1);

namespace Prooph\EventMachineTest;

use Interop\Http\Server\RequestHandlerInterface;
use Prooph\Common\Event\ProophActionEventEmitter;
use Prooph\Common\Messaging\Message;
use Prooph\EventMachine\Container\ContainerChain;
use Prooph\EventMachine\Container\EventMachineContainer;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\Persistence\DocumentStore\InMemoryDocumentStore;
use Prooph\EventMachine\Persistence\Stream;
use Prooph\EventMachine\Projecting\AggregateProjector;
use Prooph\EventMachineTest\Data\Stubs\TestIdentityVO;
use Prooph\EventStore\ActionEventEmitterEventStore;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\InMemoryEventStore;
use Prooph\EventStore\Projection\InMemoryProjectionManager;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalActionEventEmitterEventStore;
use Prooph\EventStore\TransactionalEventStore;
use Prooph\ServiceBus\Async\MessageProducer;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\QueryBus;
use ProophExample\Aggregate\Aggregate;
use ProophExample\Aggregate\CacheableUserDescription;
use ProophExample\Aggregate\UserDescription;
use ProophExample\Aggregate\UserState;
use ProophExample\Messaging\Command;
use ProophExample\Messaging\Event;
use ProophExample\Messaging\MessageDescription;
use ProophExample\Messaging\Query;
use ProophExample\Resolver\GetUserResolver;
use ProophExample\Resolver\GetUsersResolver;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use React\Promise\Deferred;
use Zend\Diactoros\Request;
use Zend\Diactoros\ServerRequest;

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
     * @var QueryBus
     */
    private $queryBus;

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
        $this->queryBus = new QueryBus();

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

        $this->appContainer->has(EventMachine::SERVICE_ID_QUERY_BUS)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_QUERY_BUS)->will(function ($args) use ($self) {
            return $self->queryBus;
        });

        $this->appContainer->has(EventMachine::SERVICE_ID_MESSAGE_FACTORY)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_JSON_SCHEMA_ASSERTION)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_SNAPSHOT_STORE)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_ASYNC_EVENT_PRODUCER)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_DOCUMENT_STORE)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_GRAPHQL_TYPE_CONFIG_DECORATOR)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_GRAPHQL_FIELD_RESOLVER)->willReturn(false);

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
        $this->eventBus = null;
        $this->queryBus = null;
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
    public function it_dispatches_a_known_query()
    {
        $getUserResolver = new class() {
            public function __invoke(Message $getUser, Deferred $deferred)
            {
                $deferred->resolve([
                    UserDescription::IDENTIFIER => $getUser->payload()[UserDescription::IDENTIFIER],
                    UserDescription::USERNAME => 'Alex',
                ]);
            }
        };

        $this->appContainer->has(GetUserResolver::class)->willReturn(true);
        $this->appContainer->get(GetUserResolver::class)->will(function ($args) use ($getUserResolver) {
            return $getUserResolver;
        });

        $this->eventMachine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $getUser = $this->eventMachine->messageFactory()->createMessageFromArray(
            Query::GET_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
            ]]
        );

        $promise = $this->eventMachine->bootstrap()->dispatch($getUser);

        $userData = null;

        $promise->done(function (array $data) use (&$userData) {
            $userData = $data;
        });

        self::assertEquals([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
        ], $userData);
    }

    /**
     * @test
     */
    public function it_allows_queries_without_payload()
    {
        $getUsersResolver = new class() {
            public function __invoke(Message $getUsers, Deferred $deferred)
            {
                $deferred->resolve([
                    [
                        UserDescription::IDENTIFIER => '123',
                        UserDescription::USERNAME => 'Alex',
                    ]
                ]);
            }
        };

        $this->appContainer->has(GetUsersResolver::class)->willReturn(true);
        $this->appContainer->get(GetUsersResolver::class)->will(function ($args) use ($getUsersResolver) {
            return $getUsersResolver;
        });

        $this->eventMachine->initialize($this->containerChain);

        $getUsers = $this->eventMachine->messageFactory()->createMessageFromArray(
            Query::GET_USERS,
            ['payload' => []]
        );

        $promise = $this->eventMachine->bootstrap()->dispatch($getUsers);

        $userList = null;

        $promise->done(function (array $data) use (&$userList) {
            $userList = $data;
        });

        self::assertEquals([
            [
            UserDescription::IDENTIFIER => '123',
            UserDescription::USERNAME => 'Alex',
            ]
        ], $userList);
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
                Command::DO_NOTHING => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
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
            ],
            'queries' => [
                Query::GET_USER => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                ]),
                Query::GET_USERS => null,
            ]
            ],
            $this->eventMachine->messageSchemas()
        );
    }

    /**
     * @test
     */
    public function it_watches_write_model_stream()
    {
        $documentStore = new InMemoryDocumentStore();

        $aggregateProjector = new AggregateProjector($documentStore, $this->eventMachine);

        $eventStore = new ActionEventEmitterEventStore(
            new InMemoryEventStore(),
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );

        $eventStore->create(new \Prooph\EventStore\Stream(new StreamName('event_stream'), new \ArrayIterator([])));

        $this->appContainer->has(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(new InMemoryProjectionManager(
            $eventStore
        ));
        $this->appContainer->get(EventMachine::SERVICE_ID_EVENT_STORE)->will(function ($args) use ($eventStore) {
            return $eventStore;
        });

        $this->appContainer->has(AggregateProjector::class)->willReturn(true);
        $this->appContainer->get(AggregateProjector::class)->will(function ($args) use ($aggregateProjector) {
            return $aggregateProjector;
        });

        $this->eventMachine->watch(Stream::ofWriteModel())
            ->with(AggregateProjector::generateProjectionName(Aggregate::USER), AggregateProjector::class)
            ->filterAggregateType(Aggregate::USER)
            ->storeDocumentsOfType('UserState', JsonSchema::object([
                'id' => ['type' => 'string'],
                'username' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'failed' => ['type' => ["boolean", "null"]]
            ]));

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

        $this->eventMachine->runProjections(false);

        $collection = AggregateProjector::generateCollectionName(
            $this->eventMachine->appVersion(),
            AggregateProjector::generateProjectionName(Aggregate::USER)
        );

        $userState = $documentStore->getDoc($collection, $userId);

        $this->assertNotNull($userState);

        $this->assertEquals([
            'id' => $userId,
            'username' => 'Alex',
            'email' => 'contact@prooph.de',
            'failed' => null
        ], $userState);
    }

    /**
     * @test
     */
    public function it_passes_registered_types_to_json_schema_assertion()
    {
        $this->eventMachine->registerType('UserState', JsonSchema::object([
            'id' => JsonSchema::string(['minLength' => 3]),
            'email' => JsonSchema::string(['format' => 'email'])
        ], [], true));

        $this->eventMachine->initialize($this->containerChain);

        $this->eventMachine->bootstrap();

        $visitorSchema = JsonSchema::object(['role' => JsonSchema::enum(['guest'])], [], true);

        $identifiedVisitorSchema = ['allOf' => [
            JsonSchema::typeRef('UserState'),
            $visitorSchema
        ]];

        $guest = ['id' => '123', 'role' => 'guest'];

        $this->eventMachine->jsonSchemaAssertion()->assert('Guest', $guest, $visitorSchema);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of IdentifiedVisitor failed: \[email\] The property email is required/');

        $this->eventMachine->jsonSchemaAssertion()->assert('IdentifiedVisitor', $guest, $identifiedVisitorSchema);

    }

    /**
     * @test
     */
    public function it_uses_immutable_record_info_to_register_a_type()
    {
        $this->eventMachine->registerType(TestIdentityVO::class);

        $this->eventMachine->initialize($this->containerChain)->bootstrap(EventMachine::ENV_TEST, true);

        $userIdentityData = [
            'identity' => [
                'email' => 'test@test.local',
                'password' => 12345
            ]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of UserIdentityData failed: \[identity.password\] Integer value found, but a string is required/');

        $this->eventMachine->jsonSchemaAssertion()->assert('UserIdentityData', $userIdentityData, JsonSchema::object([
            'identity' => JsonSchema::typeRef(TestIdentityVO::type())
        ]));
    }

    /**
     * @test
     */
    public function it_sets_up_a_graphql_server()
    {
        $getUserResolver = new class() {
            public function __invoke(Message $getUser, Deferred $deferred)
            {
                $deferred->resolve([
                    UserDescription::IDENTIFIER => $getUser->payload()[UserDescription::IDENTIFIER],
                    UserDescription::USERNAME => 'Alex',
                ]);
            }
        };

        $this->appContainer->has(GetUserResolver::class)->willReturn(true);
        $this->appContainer->get(GetUserResolver::class)->will(function ($args) use ($getUserResolver) {
            return $getUserResolver;
        });

        $this->eventMachine->initialize($this->containerChain);

        $server = $this->eventMachine->bootstrap(EventMachine::ENV_TEST, true)->graphqlServer();

        $this->assertInstanceOf(RequestHandlerInterface::class, $server);

        $userId = Uuid::uuid4()->toString();

        $queryName = Query::GET_USER;
        $identifier = UserDescription::IDENTIFIER;
        $username = UserDescription::USERNAME;

        $query = "{ $queryName({$identifier}: \"$userId\") { $username } }";

        $stream = new \Zend\Diactoros\CallbackStream(function () use ($query) {
            return $query;
        });

        $request = new ServerRequest([], [], "/graphql", 'POST', $stream, [
            'Content-Type' => 'application/graphql'
        ]);

        $response = $server->handle($request);

        $this->assertEquals(json_encode([
            "data" => ["GetUser" => [$username => "Alex"]]
        ]), (string)$response->getBody());
    }

    /**
     * @test
     */
    public function it_handles_queries_without_args_with_graphql()
    {
        $getUsersResolver = new class() {
            public function __invoke(Message $getUsers, Deferred $deferred)
            {
                $deferred->resolve([
                    [
                        UserDescription::IDENTIFIER => '123',
                        UserDescription::USERNAME => 'Alex',
                    ]
                ]);
            }
        };

        $this->appContainer->has(GetUsersResolver::class)->willReturn(true);
        $this->appContainer->get(GetUsersResolver::class)->will(function ($args) use ($getUsersResolver) {
            return $getUsersResolver;
        });

        $this->eventMachine->initialize($this->containerChain);

        $server = $this->eventMachine->bootstrap(EventMachine::ENV_TEST, true)->graphqlServer();

        $this->assertInstanceOf(RequestHandlerInterface::class, $server);

        $queryName = Query::GET_USERS;
        $username = UserDescription::USERNAME;

        $query = "{ $queryName { $username } }";

        $stream = new \Zend\Diactoros\CallbackStream(function () use ($query) {
            return $query;
        });

        $request = new ServerRequest([], [], "/graphql", 'POST', $stream, [
            'Content-Type' => 'application/graphql'
        ]);

        $response = $server->handle($request);

        $this->assertEquals(json_encode([
            "data" => ["GetUsers" => [[$username => "Alex"]]]
        ]), (string)$response->getBody());
    }

    /**
     * @test
     */
    public function it_sets_app_version()
    {
        $this->eventMachine->initialize($this->containerChain, '0.2.0');

        $this->eventMachine->bootstrap();

        $this->assertEquals('0.2.0', $this->eventMachine->appVersion());
    }
}
