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

use Prooph\Common\Event\ProophActionEventEmitter;
use Prooph\Common\Messaging\Message as ProophMessage;
use Prooph\EventMachine\Container\ContainerChain;
use Prooph\EventMachine\Container\EventMachineContainer;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Exception\TransactionCommitFailed;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type\EmailType;
use Prooph\EventMachine\JsonSchema\Type\EnumType;
use Prooph\EventMachine\JsonSchema\Type\StringType;
use Prooph\EventMachine\JsonSchema\Type\UuidType;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Messaging\MessageBag;
use Prooph\EventMachine\Messaging\MessageDispatcher;
use Prooph\EventMachine\Messaging\MessageFactoryAware;
use Prooph\EventMachine\Persistence\DocumentStore;
use Prooph\EventMachine\Persistence\InMemoryConnection;
use Prooph\EventMachine\Persistence\InMemoryEventStore;
use Prooph\EventMachine\Persistence\Stream;
use Prooph\EventMachine\Persistence\TransactionManager;
use Prooph\EventMachine\Projecting\AggregateProjector;
use Prooph\EventMachine\Projecting\InMemory\InMemoryProjectionManager;
use Prooph\EventMachine\Runtime\Flavour;
use Prooph\EventMachineTest\Data\Stubs\TestIdentityVO;
use Prooph\EventStore\ActionEventEmitterEventStore;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalActionEventEmitterEventStore;
use Prooph\EventStore\TransactionalEventStore;
use Prooph\ServiceBus\Async\MessageProducer;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\QueryBus;
use ProophExample\FunctionalFlavour\Api\Command;
use ProophExample\FunctionalFlavour\Api\Event;
use ProophExample\FunctionalFlavour\Api\Query;
use ProophExample\FunctionalFlavour\Event\UsernameChanged;
use ProophExample\FunctionalFlavour\Event\UserRegistered;
use ProophExample\OopFlavour\Aggregate\UserDescription;
use ProophExample\PrototypingFlavour\Aggregate\Aggregate;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;

abstract class EventMachineTestAbstract extends BasicTestCase
{
    abstract protected function loadEventMachineDescriptions(EventMachine $eventMachine);

    abstract protected function getFlavour(): Flavour;

    abstract protected function getRegisteredUsersProjector(DocumentStore $documentStore);

    abstract protected function getUserRegisteredListener(MessageDispatcher $messageDispatcher);

    abstract protected function getUserResolver(array $cachedUserState): callable;

    abstract protected function getUsersResolver(array $cachedUsers): callable;

    abstract protected function assertLoadedUserState($userState): void;

    /**
     * @var ObjectProphecy
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

    /**
     * @var TransactionManager
     */
    private $transactionManager;

    /**
     * InMemoryConnection
     *
     * @var InMemoryConnection
     */
    private $inMemoryConnection;

    /**
     * @var Flavour
     */
    private $flavour;

    protected function setUp()
    {
        $this->eventMachine = new EventMachine();

        $this->loadEventMachineDescriptions($this->eventMachine);

        $this->eventStore = $this->prophesize(EventStore::class);

        $this->actionEventEmitterEventStore = new ActionEventEmitterEventStore(
            $this->eventStore->reveal(),
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );
        $this->commandBus = new CommandBus();
        $this->eventBus = new EventBus();
        $this->queryBus = new QueryBus();
        $this->inMemoryConnection = new InMemoryConnection();
        $this->flavour = $this->getFlavour();

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

        $this->appContainer->has(EventMachine::SERVICE_ID_TRANSACTION_MANAGER)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_TRANSACTION_MANAGER)->will(function ($args) use ($self) {
            return $self->transactionManager;
        });

        $this->appContainer->has(EventMachine::SERVICE_ID_MESSAGE_FACTORY)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_JSON_SCHEMA_ASSERTION)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_SNAPSHOT_STORE)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_ASYNC_EVENT_PRODUCER)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_DOCUMENT_STORE)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_FLAVOUR)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_FLAVOUR)->will(function ($args) use ($self) {
            return $self->flavour;
        });

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
        $this->transactionManager = null;
        $this->inMemoryConnection = null;
        $this->flavour = null;
    }

    protected function setUpAggregateProjector(
        DocumentStore $documentStore,
        EventStore $eventStore,
        StreamName $streamName
    ): void {
        $aggregateProjector = new AggregateProjector($documentStore, $this->eventMachine);

        $eventStore->create(new \Prooph\EventStore\Stream($streamName, new \ArrayIterator([])));

        $this->appContainer->has(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(new InMemoryProjectionManager(
            $eventStore,
            $this->inMemoryConnection
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
            ->filterAggregateType(Aggregate::USER);
    }

    protected function setUpRegisteredUsersProjector(
        DocumentStore $documentStore,
        EventStore $eventStore,
        StreamName $streamName
    ): void {
        $projector = $this->getRegisteredUsersProjector($documentStore);

        $eventStore->create(new \Prooph\EventStore\Stream($streamName, new \ArrayIterator([])));

        $this->appContainer->has(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(new InMemoryProjectionManager(
            $eventStore,
            $this->inMemoryConnection
        ));
        $this->appContainer->get(EventMachine::SERVICE_ID_EVENT_STORE)->will(function ($args) use ($eventStore) {
            return $eventStore;
        });

        $this->appContainer->has('Test.Projector.RegisteredUsers')->willReturn(true);
        $this->appContainer->get('Test.Projector.RegisteredUsers')->will(function ($args) use ($projector) {
            return $projector;
        });

        $this->eventMachine->watch(Stream::ofWriteModel())
            ->with('registered_users', 'Test.Projector.RegisteredUsers')
            ->filterEvents([
                \ProophExample\PrototypingFlavour\Messaging\Event::USER_WAS_REGISTERED,
            ]);
    }

    /**
     * @test
     */
    public function it_dispatches_a_known_command()
    {
        $recordedEvents = [];

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = \iterator_to_array($args[1]);
        });

        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
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

        $this->assertUserWasRegistered($event, $registerUser, $userId);
    }

    /**
     * @test
     */
    public function it_dispatches_a_known_query()
    {
        $userId = Uuid::uuid4()->toString();

        $getUserResolver = $this->getUserResolver([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
        ]);

        $this->appContainer->has(\get_class($getUserResolver))->willReturn(true);
        $this->appContainer->get(\get_class($getUserResolver))->will(function ($args) use ($getUserResolver) {
            return $getUserResolver;
        });

        $this->eventMachine->initialize($this->containerChain);

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
        $getUsersResolver = $this->getUsersResolver([
            [
                UserDescription::IDENTIFIER => '123',
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ],
        ]);

        $this->appContainer->has(\get_class($getUsersResolver))->willReturn(true);
        $this->appContainer->get(\get_class($getUsersResolver))->will(function ($args) use ($getUsersResolver) {
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
                UserDescription::EMAIL => 'contact@prooph.de',
            ],
        ], $userList);
    }

    /**
     * @test
     */
    public function it_creates_message_on_dispatch_if_only_name_and_payload_is_given()
    {
        $recordedEvents = [];

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = \iterator_to_array($args[1]);
        });

        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
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
    }

    /**
     * @test
     */
    public function it_can_handle_command_for_existing_aggregate()
    {
        $recordedEvents = [];

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = \array_merge($recordedEvents, \iterator_to_array($args[1]));
        });

        $this->eventStore->load(new StreamName('event_stream'), 1, null, Argument::type(MetadataMatcher::class))->will(function ($args) use (&$recordedEvents) {
            return new \ArrayIterator([$recordedEvents[0]]);
        });

        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->eventMachine->on(Event::USERNAME_WAS_CHANGED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->eventMachine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $this->eventMachine->bootstrap()->dispatch(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
        ]);

        $this->eventMachine->dispatch(Command::CHANGE_USERNAME, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'John',
        ]);

        self::assertCount(2, $recordedEvents);
        self::assertCount(2, $publishedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[1];
        self::assertEquals(Event::USERNAME_WAS_CHANGED, $event->messageName());
    }

    /**
     * @test
     */
    public function it_enables_async_switch_message_router_if_container_has_a_producer()
    {
        $producedEvents = [];

        $eventMachine = $this->eventMachine;

        $messageProducer = $this->prophesize(MessageProducer::class);
        $messageProducer->__invoke(Argument::type(ProophMessage::class), Argument::exact(null))
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
            $recordedEvents = \iterator_to_array($args[1]);
        });

        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
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

        $this->assertUserWasRegistered($event, $registerUser, $userId);

        self::assertEquals(Event::USER_WAS_REGISTERED, $producedEvents[0]->messageName());
        self::assertEquals(Event::USER_WAS_REGISTERED, $publishedEvents[0]->messageName());
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
                        'email' => 'tester@test.com',
                    ],
                    'metadata' => [
                        '_aggregate_id' => $userId,
                        '_aggregate_type' => Aggregate::USER,
                        '_aggregate_version' => 1,
                    ],
                ]),
            ]);
        });

        $userState = $eventMachine->bootstrap()->loadAggregateState(Aggregate::USER, $userId);

        $this->assertLoadedUserState($userState);
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
            $recordedEvents = \iterator_to_array($args[1]);
        });

        $this->eventStore->commit()->shouldBeCalled();

        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
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
    }

    /**
     * @test
     */
    public function it_provides_message_schemas()
    {
        $this->eventMachine->initialize($this->containerChain);

        $userId = new UuidType();

        $username = (new StringType())->withMinLength(1);

        $userDataSchema = JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
            UserDescription::EMAIL => new EmailType(),
        ], [
            'shouldFail' => JsonSchema::boolean(),
        ]);

        $filterInput = JsonSchema::object([
            'username' => JsonSchema::nullOr(JsonSchema::string()),
            'email' => JsonSchema::nullOr(JsonSchema::email()),
        ]);

        self::assertEquals([
            'commands' => [
                Command::REGISTER_USER => $userDataSchema->toArray(),
                Command::CHANGE_USERNAME => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                    UserDescription::USERNAME => $username,
                ])->toArray(),
                Command::DO_NOTHING => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                ])->toArray(),
            ],
            'events' => [
                Event::USER_WAS_REGISTERED => $userDataSchema->toArray(),
                Event::USERNAME_WAS_CHANGED => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                    'oldName' => $username,
                    'newName' => $username,
                ])->toArray(),
                Event::USER_REGISTRATION_FAILED => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                ])->toArray(),
            ],
            'queries' => [
                Query::GET_USER => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                ])->toArray(),
                Query::GET_USERS => JsonSchema::object([])->toArray(),
                Query::GET_FILTERED_USERS => JsonSchema::object([], [
                    'filter' => $filterInput,
                ])->toArray(),
            ],
        ],
            $this->eventMachine->messageSchemas()
        );
    }

    /**
     * @test
     */
    public function it_builds_a_message_box_schema(): void
    {
        $this->eventMachine->initialize($this->containerChain);

        $this->eventMachine->bootstrap();

        $userId = new UuidType();

        $username = (new StringType())->withMinLength(1);

        $userDataSchema = JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
            UserDescription::EMAIL => new EmailType(),
        ], [
            'shouldFail' => JsonSchema::boolean(),
        ]);

        $filterInput = JsonSchema::object([
            'username' => JsonSchema::nullOr(JsonSchema::string()),
            'email' => JsonSchema::nullOr(JsonSchema::email()),
        ]);

        $queries = [
            Query::GET_USER => JsonSchema::object([
                UserDescription::IDENTIFIER => $userId,
            ])->toArray(),
            Query::GET_USERS => JsonSchema::object([])->toArray(),
            Query::GET_FILTERED_USERS => JsonSchema::object([], [
                'filter' => $filterInput,
            ])->toArray(),
        ];

        $queries[Query::GET_USER]['response'] = JsonSchema::typeRef('User')->toArray();
        $queries[Query::GET_USERS]['response'] = JsonSchema::array(JsonSchema::typeRef('User'))->toArray();
        $queries[Query::GET_FILTERED_USERS]['response'] = JsonSchema::array(JsonSchema::typeRef('User'))->toArray();

        $this->assertEquals([
            'title' => 'Event Machine MessageBox',
            'description' => 'A mechanism for handling prooph messages',
            '$schema' => 'http://json-schema.org/draft-06/schema#',
            'type' => 'object',
            'properties' => [
                'commands' => [
                    Command::REGISTER_USER => $userDataSchema->toArray(),
                    Command::CHANGE_USERNAME => JsonSchema::object([
                        UserDescription::IDENTIFIER => $userId,
                        UserDescription::USERNAME => $username,
                    ])->toArray(),
                    Command::DO_NOTHING => JsonSchema::object([
                        UserDescription::IDENTIFIER => $userId,
                    ])->toArray(),
                ],
                'events' => [
                    Event::USER_WAS_REGISTERED => $userDataSchema->toArray(),
                    Event::USERNAME_WAS_CHANGED => JsonSchema::object([
                        UserDescription::IDENTIFIER => $userId,
                        'oldName' => $username,
                        'newName' => $username,
                    ])->toArray(),
                    Event::USER_REGISTRATION_FAILED => JsonSchema::object([
                        UserDescription::IDENTIFIER => $userId,
                    ])->toArray(),
                ],
                'queries' => $queries,
            ],
            'definitions' => [
                'User' => $userDataSchema->toArray(),
            ],
        ], $this->eventMachine->messageBoxSchema());
    }

    /**
     * @test
     */
    public function it_watches_write_model_stream()
    {
        $documentStore = new DocumentStore\InMemoryDocumentStore(new InMemoryConnection());

        $eventStore = new ActionEventEmitterEventStore(
            new InMemoryEventStore($this->inMemoryConnection),
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );

        $this->setUpAggregateProjector($documentStore, $eventStore, new StreamName('event_stream'));

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

        $userState = $documentStore->getDoc(
            $this->getAggregateCollectionName(Aggregate::USER),
            $userId
        );

        $this->assertNotNull($userState);

        $this->assertEquals([
            'userId' => $userId,
            'username' => 'Alex',
            'email' => 'contact@prooph.de',
            'failed' => null,
        ], $userState);
    }

    /**
     * @test
     */
    public function it_forwards_projector_call_to_flavour()
    {
        $documentStore = new DocumentStore\InMemoryDocumentStore(new InMemoryConnection());

        $eventStore = new ActionEventEmitterEventStore(
            new InMemoryEventStore($this->inMemoryConnection),
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );

        $this->setUpRegisteredUsersProjector($documentStore, $eventStore, new StreamName('event_stream'));

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

        //We expect RegisteredUsersProjector to use collection naming convention: <projection_name>_<app_version>
        $userState = $documentStore->getDoc(
            'registered_users_0.1.0',
            $userId
        );

        $this->assertNotNull($userState);

        $this->assertEquals([
            'userId' => $userId,
            'username' => 'Alex',
            'email' => 'contact@prooph.de',
        ], $userState);
    }

    /**
     * @test
     */
    public function it_invokes_event_listener_using_flavour()
    {
        $messageDispatcher = $this->prophesize(MessageDispatcher::class);

        $newCmdName = null;
        $newCmdPayload = null;
        $messageDispatcher->dispatch(Argument::any(), Argument::any())->will(function ($args) use (&$newCmdName, &$newCmdPayload) {
            $newCmdName = $args[0] ?? null;
            $newCmdPayload = $args[1] ?? null;
        });

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, 'Test.Listener.UserRegistered');

        $listener = $this->getUserRegisteredListener($messageDispatcher->reveal());

        $this->appContainer->has('Test.Listener.UserRegistered')->willReturn(true);
        $this->appContainer->get('Test.Listener.UserRegistered')->will(function ($args) use ($listener) {
            return $listener;
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

        $this->assertNotNull($newCmdName);
        $this->assertNotNull($newCmdPayload);

        $this->assertEquals('SendWelcomeEmail', $newCmdName);
        $this->assertEquals(['email' => 'contact@prooph.de'], $newCmdPayload);
    }

    /**
     * @test
     */
    public function it_passes_registered_types_to_json_schema_assertion()
    {
        $this->eventMachine->registerType('UserState', JsonSchema::object([
            'id' => JsonSchema::string(['minLength' => 3]),
            'email' => JsonSchema::string(['format' => 'email']),
        ], [], true));

        $this->eventMachine->initialize($this->containerChain);

        $this->eventMachine->bootstrap();

        $visitorSchema = JsonSchema::object(['role' => JsonSchema::enum(['guest'])], [], true);

        $identifiedVisitorSchema = ['allOf' => [
            JsonSchema::typeRef('UserState')->toArray(),
            $visitorSchema,
        ]];

        $guest = ['id' => '123', 'role' => 'guest'];

        $this->eventMachine->jsonSchemaAssertion()->assert('Guest', $guest, $visitorSchema->toArray());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of IdentifiedVisitor failed: \[email\] The property email is required/');

        $this->eventMachine->jsonSchemaAssertion()->assert('IdentifiedVisitor', $guest, $identifiedVisitorSchema);
    }

    /**
     * @test
     */
    public function it_registers_enum_type_as_type()
    {
        $colorSchema = new EnumType('red', 'blue', 'yellow');

        $this->eventMachine->registerEnumType('color', $colorSchema);

        $this->eventMachine->initialize($this->containerChain);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of ball failed: \[color\] Does not have a value in the enumeration \["red","blue","yellow"\]/');

        $ballSchema = JsonSchema::object([
            'color' => JsonSchema::typeRef('color'),
        ])->toArray();

        $this->eventMachine->jsonSchemaAssertion()->assert('ball', ['color' => 'green'], $ballSchema);
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
                'password' => 12345,
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of UserIdentityData failed: \[identity.password\] Integer value found, but a string is required/');

        $this->eventMachine->jsonSchemaAssertion()->assert('UserIdentityData', $userIdentityData, JsonSchema::object([
            'identity' => JsonSchema::typeRef(TestIdentityVO::__type()),
        ])->toArray());
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

    /**
     * @test
     */
    public function it_dispatches_a_known_command_with_immediate_consistency(): void
    {
        $documentStore = new DocumentStore\InMemoryDocumentStore($this->inMemoryConnection);

        $inMemoryEventStore = new InMemoryEventStore($this->inMemoryConnection);

        $eventStore = new ActionEventEmitterEventStore(
            $inMemoryEventStore,
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );

        $streamName = new StreamName('event_stream');

        $this->setUpAggregateProjector($documentStore, $eventStore, $streamName);

        $this->transactionManager = new TransactionManager($this->inMemoryConnection);
        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->eventMachine->setImmediateConsistency(true);
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

        $recordedEvents = $inMemoryEventStore->load($streamName);

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        $this->assertUserWasRegistered($event, $registerUser, $userId);

        $userState = $documentStore->getDoc(
            $this->getAggregateCollectionName(Aggregate::USER),
            $userId
        );

        $this->assertNotNull($userState);

        $this->assertEquals([
            'userId' => $userId,
            'username' => 'Alex',
            'email' => 'contact@prooph.de',
            'failed' => null,
        ], $userState);
    }

    /**
     * @test
     */
    public function it_rolls_back_events_and_projection_with_immediate_consistency(): void
    {
        $documentStore = $this->prophesize(DocumentStore::class);
        $documentStore->hasCollection(Argument::type('string'))->willReturn(false);
        $documentStore->addCollection(Argument::type('string'))->shouldBeCalled();
        $documentStore
            ->upsertDoc(Argument::type('string'), Argument::type('string'), Argument::type('array'))
            ->willThrow(new \RuntimeException('projection error'));

        $inMemoryEventStore = new InMemoryEventStore($this->inMemoryConnection);

        $eventStore = new ActionEventEmitterEventStore(
            $inMemoryEventStore,
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );

        $streamName = new StreamName('event_stream');

        $this->setUpAggregateProjector($documentStore->reveal(), $eventStore, $streamName);

        $this->transactionManager = new TransactionManager($this->inMemoryConnection);
        $publishedEvents = [];

        $this->eventMachine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->eventMachine->setImmediateConsistency(true);
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

        $exceptionThrown = false;

        // more tests after exception needed
        try {
            $this->eventMachine->bootstrap()->dispatch($registerUser);
        } catch (TransactionCommitFailed $e) {
            $exceptionThrown = true;
        }
        $this->assertTrue($exceptionThrown);
        $this->assertEmpty(\iterator_to_array($eventStore->load($streamName)));
    }

    /**
     * @test
     */
    public function it_switches_action_event_emitter_with_immediate_consistency(): void
    {
        $documentStore = new DocumentStore\InMemoryDocumentStore($this->inMemoryConnection);

        $inMemoryEventStore = new InMemoryEventStore($this->inMemoryConnection);

        //A TransactionalActionEventEmitterEventStore conflicts with the immediate consistency mode
        //because the EventPublisher listens on commit event, but it never happens due to transaction managed
        //outside of the event store
        //Event Machine needs to take care of it
        $eventStore = new TransactionalActionEventEmitterEventStore(
            $inMemoryEventStore,
            new ProophActionEventEmitter(TransactionalActionEventEmitterEventStore::ALL_EVENTS)
        );

        $streamName = new StreamName('event_stream');

        $this->setUpAggregateProjector($documentStore, $eventStore, $streamName);

        $this->transactionManager = new TransactionManager($this->inMemoryConnection);
        $publishedEvents = [];

        $this->eventMachine->setImmediateConsistency(true);
        $this->eventMachine->initialize($this->containerChain);

        $this->expectException(RuntimeException::class);

        $this->eventMachine->bootstrap();
    }

    private function assertUserWasRegistered(
        Message $event,
        Message $registerUser,
        string $userId
    ): void {
        self::assertEquals(Event::USER_WAS_REGISTERED, $event->messageName());
        self::assertEquals([
            '_causation_id' => $registerUser->uuid()->toString(),
            '_causation_name' => $registerUser->messageName(),
            '_aggregate_version' => 1,
            '_aggregate_id' => $userId,
            '_aggregate_type' => 'User',
        ], $event->metadata());
    }

    private function getAggregateCollectionName(string $aggregate): string
    {
        return AggregateProjector::generateCollectionName(
            $this->eventMachine->appVersion(),
            AggregateProjector::generateProjectionName($aggregate)
        );
    }

    private function convertToEventMachineMessage($event): Message
    {
        $flavour = $this->getFlavour();
        if ($flavour instanceof MessageFactoryAware) {
            $flavour->setMessageFactory($this->eventMachine->messageFactory());
        }

        switch (\get_class($event)) {
            case UserRegistered::class:
                return $flavour->prepareNetworkTransmission(new MessageBag(
                    Event::USER_WAS_REGISTERED,
                    Message::TYPE_EVENT,
                    $event
                ));
            case UsernameChanged::class:
                return $flavour->prepareNetworkTransmission(new MessageBag(
                    Event::USERNAME_WAS_CHANGED,
                    Message::TYPE_EVENT,
                    $event
                ));
            default:
                return $event;
        }
    }
}
