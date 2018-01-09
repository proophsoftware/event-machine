<?php
declare(strict_types = 1);

namespace Prooph\EventMachine;

use Prooph\Common\Event\ActionEvent;
use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Aggregate\AggregateTestHistoryEventEnricher;
use Prooph\EventMachine\Aggregate\ClosureAggregateTranslator;
use Prooph\EventMachine\Aggregate\Exception\AggregateNotFound;
use Prooph\EventMachine\Aggregate\GenericAggregateRoot;
use Prooph\EventMachine\Commanding\CommandPreProcessor;
use Prooph\EventMachine\Commanding\CommandProcessorDescription;
use Prooph\EventMachine\Commanding\CommandToProcessorRouter;
use Prooph\EventMachine\Container\ContainerChain;
use Prooph\EventMachine\Container\TestEnvContainer;
use Prooph\EventMachine\Http\MessageBox;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Prooph\EventMachine\JsonSchema\JustinRainbowJsonSchemaAssertion;
use Prooph\EventMachine\Messaging\GenericJsonSchemaMessageFactory;
use Prooph\EventMachine\Persistence\Stream;
use Prooph\EventMachine\Projecting\ProjectionDescription;
use Prooph\EventMachine\Projecting\ProjectionRunner;
use Prooph\EventMachine\Projecting\Projector;
use Prooph\EventMachine\Querying\QueryDescription;
use Prooph\EventSourcing\Aggregate\AggregateRepository;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventStore\ActionEventEmitterEventStore;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalActionEventEmitterEventStore;
use Prooph\EventStoreBusBridge\EventPublisher;
use Prooph\EventStoreBusBridge\TransactionManager;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\Plugin\Router\AsyncSwitchMessageRouter;
use Prooph\ServiceBus\Plugin\Router\EventRouter;
use Prooph\ServiceBus\Plugin\ServiceLocatorPlugin;
use Psr\Container\ContainerInterface;

final class EventMachine
{
    const SERVICE_ID_EVENT_STORE = 'EventMachine.EventStore';
    const SERVICE_ID_SNAPSHOT_STORE = 'EventMachine.SnapshotStore';
    const SERVICE_ID_COMMAND_BUS = 'EventMachine.CommandBus';
    const SERVICE_ID_EVENT_BUS = 'EventMachine.EventBus';
    const SERVICE_ID_PROJECTION_MANAGER = 'EventMachine.ProjectionManager';
    const SERVICE_ID_DOCUMENT_STORE = 'EventMachine.DocumentStore';
    const SERVICE_ID_ASYNC_EVENT_PRODUCER = 'EventMachine.AsyncEventProducer';
    const SERVICE_ID_MESSAGE_FACTORY = 'EventMachine.MessageFactory';
    const SERVICE_ID_JSON_SCHEMA_ASSERTION = 'EventMachine.JsonSchemaAssertion';

    /**
     * Map of command names and corresponding json schema of payload
     *
     * Json schema can be passed as array or path to schema file
     *
     * @var array
     */
    private $commandMap = [];

    /**
     * Map of command names and corresponding list of preprocessors given as either service id string or callable
     *
     * @var string[]|CommandPreProcessor[]
     */
    private $commandPreProcessors = [];

    /**
     * @var array
     */
    private $commandRouting = [];

    /**
     * @var array
     */
    private $compiledCommandRouting;

    /**
     * @var array
     */
    private $aggregateDescriptions;

    /**
     * Map of event names and corresponding json schema of payload
     *
     * Json schema can be passed as array or path to schema file
     *
     * @var array
     */
    private $eventMap = [];

    /**
     * Map of event names and corresponding list of listeners given as either service id string or callable
     *
     * @var string|callable[]
     */
    private $eventRouting = [];

    /**
     * Map of projection names and corresponding projection descriptions
     *
     * @var ProjectionDescription[] indexed by projection name
     */
    private $projectionMap = [];

    /**
     * @var QueryDescription[] list of QueryDescription indexed by query name
     */
    private $queryDescriptions = [];

    /**
     * @var array list of compiled query descriptions indexed by query name
     */
    private $compiledQueryDescriptions = [];

    /**
     * @var array list of query names and corresponding payload schemas
     */
    private $queryMap = [];

    /**
     * @var array list of type definitions indexed by type name
     */
    private $schemaTypes = [];

    /**
     * @var array
     */
    private $compiledProjectionDescriptions = [];

    /**
     * @var ContainerInterface
     */
    private $container;

    private $initialized = false;

    private $bootstrapped = false;

    private $testMode = false;

    private $appVersion = '';

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var JsonSchemaAssertion
     */
    private $jsonSchemaAssertion;

    /**
     * @var MessageBox
     */
    private $httpMessageBox;

    private $testSessionEvents = [];

    private $projectionRunner;

    public static function fromCachedConfig(array $config, ContainerInterface $container): self
    {
        $self = new self();

        if(!array_key_exists('commandMap', $config)) {
            throw new \InvalidArgumentException("Missing key commandMap in cached event machine config");
        }

        if(!array_key_exists('eventMap', $config)) {
            throw new \InvalidArgumentException("Missing key eventMap in cached event machine config");
        }

        if(!array_key_exists('compiledCommandRouting', $config)) {
            throw new \InvalidArgumentException("Missing key compiledCommandRouting in cached event machine config");
        }

        if(!array_key_exists('aggregateDescriptions', $config)) {
            throw new \InvalidArgumentException("Missing key aggregateDescriptions in cached event machine config");
        }

        $self->commandMap = $config['commandMap'];
        $self->eventMap = $config['eventMap'];
        $self->compiledCommandRouting = $config['compiledCommandRouting'];
        $self->aggregateDescriptions = $config['aggregateDescriptions'];
        $self->compiledProjectionDescriptions = $config['compiledProjectionDescriptions'];
        $self->schemaTypes = $config['schemaTypes'];
        $self->appVersion = $config['appVersion'];
        $self->compiledQueryDescriptions = $config['compiledQueryDescriptions'];
        $self->queryMap = $config['queryMap'];

        $self->initialized = true;

        $self->container = $container;

        return $self;
    }

    public function load(string $description): void
    {
        $this->assertNotInitialized(__METHOD__);
        call_user_func([$description, 'describe'], $this);
    }

    public function registerCommand(string $commandName, $schemaOrPath): self
    {
        $this->assertNotInitialized(__METHOD__);
        if(array_key_exists($commandName, $this->commandMap)) {
            throw new \RuntimeException("Command $commandName was already registered.");
        }

        if(!is_array($schemaOrPath) && !is_string($schemaOrPath)) {
            throw new \InvalidArgumentException("Json schema should be passed as array or path to schema file. Got " . gettype($schemaOrPath));
        }

        $this->commandMap[$commandName] = $schemaOrPath;

        return $this;
    }

    public function registerEvent(string $eventName, $schemaOrPath): self
    {
        $this->assertNotInitialized(__METHOD__);

        if(array_key_exists($eventName, $this->eventMap)) {
            throw new \RuntimeException("Event $eventName was already registered.");
        }

        if(!is_array($schemaOrPath) && !is_string($schemaOrPath)) {
            throw new \InvalidArgumentException("Json schema should be passed as array or path to schema file. Got " . gettype($schemaOrPath));
        }

        $this->eventMap[$eventName] = $schemaOrPath;

        return $this;
    }

    public function registerQuery(string $queryName, array $payloadSchema): QueryDescription
    {
        $this->jsonSchemaAssertion()->assert("Query $queryName payload schema", $payloadSchema, JsonSchema::metaSchema());

        if($this->isKnownQuery($queryName)) {
            throw new \RuntimeException("Query $queryName was already registered");
        }

        $this->queryMap[$queryName] = $payloadSchema;
        $queryDesc = new QueryDescription($queryName, $this);
        $this->queryDescriptions[$queryName] = $queryDesc;

        return $queryDesc;
    }

    public function registerProjection(string $projectionName, ProjectionDescription $projectionDescription): void
    {
        $this->assertNotInitialized(__METHOD__);

        if($this->isKnownProjection($projectionName)) {
            throw new \RuntimeException("Projection with name $projectionName is already registered.");
        }
        $this->projectionMap[$projectionName] = $projectionDescription;
    }

    public function registerType(string $name, array $schema): void
    {
        $this->assertNotInitialized(__METHOD__);

        if($this->isKnownType($name)) {
            throw new \RuntimeException("Type $name is already registered");
        }

        //@TODO: assert that type can be converted to GraphQL type language
        $this->jsonSchemaAssertion()->assert("Type $name", $schema, JsonSchema::metaSchema());

        $this->schemaTypes[$name] = $schema;
    }

    public function preProcess(string $commandName, $preProcessor): self
    {
        $this->assertNotInitialized(__METHOD__);

        if(!$this->isKnownCommand($commandName)) {
            throw new \InvalidArgumentException("Preprocessor attached to unknown command $commandName. You should register the command first");
        }

        if(!is_string($preProcessor) && !$preProcessor instanceof CommandPreProcessor) {
            throw new \InvalidArgumentException("PreProcessor should either be a service id given as string or an instance of ".CommandPreProcessor::class.". Got "
                . (is_object($preProcessor)? get_class($preProcessor) : gettype($preProcessor)));
        }

        $this->commandPreProcessors[$commandName][] = $preProcessor;

        return $this;
    }

    public function process(string $commandName): CommandProcessorDescription
    {
        $this->assertNotInitialized(__METHOD__);
        if(array_key_exists($commandName, $this->commandRouting)) {
            throw new \BadMethodCallException("Method process was called twice for the same command: " . $commandName);
        }

        if(!array_key_exists($commandName, $this->commandMap)) {
            throw new \BadMethodCallException("Command $commandName is unknown. You should register it first.");
        }

        $this->commandRouting[$commandName] = new CommandProcessorDescription($commandName, $this);

        return $this->commandRouting[$commandName];
    }

    public function on(string $eventName, $listener): self
    {
        $this->assertNotInitialized(__METHOD__);

        if(!$this->isKnownEvent($eventName)) {
            throw new \InvalidArgumentException("Listener attached to unknown event $eventName. You should register the event first");
        }

        if(!is_string($listener) && !is_callable($listener)) {
            throw new \InvalidArgumentException("Listener should be either a service id given as string or a callable. Got "
                . (is_object($listener)? get_class($listener) : gettype($listener)));
        }

        $this->eventRouting[$eventName][] = $listener;

        return $this;
    }

    public function watch(Stream $stream): ProjectionDescription
    {
        //ProjectionDescriptions register itself using EventMachine::registerProjection within ProjectionDescription::with call
        return new ProjectionDescription($stream, $this);
    }

    public function isKnownCommand(string $commandName): bool
    {
        return array_key_exists($commandName, $this->commandMap);
    }

    public function isKnownEvent(string $eventName): bool
    {
        return array_key_exists($eventName, $this->eventMap);
    }

    public function isKnownQuery(string $queryName): bool
    {
        return array_key_exists($queryName, $this->queryMap);
    }

    public function isKnownProjection(string $projectionName): bool
    {
        return array_key_exists($projectionName, $this->projectionMap);
    }

    public function isKnownType(string $typeName): bool
    {
        return array_key_exists($typeName, $this->schemaTypes);
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function initialize(ContainerInterface $container, string $appVersion = '0.1.0'): self
    {
        $this->assertNotInitialized(__METHOD__);

        $this->determineAggregateAndRoutingDescriptions();
        $this->compileProjectionDescriptions();
        $this->compileQueryDescriptions();

        $this->initialized = true;

        $this->container = $container;

        return $this;
    }

    public function bootstrap(): self
    {
        $this->assertInitialized(__METHOD__);
        $this->assertNotBootstrapped(__METHOD__);

        $this->attachRouterToCommandBus();
        $this->setUpEventBus();
        $this->attachEventPublisherToEventStore();

        $this->bootstrapped = true;

        return $this;
    }

    /**
     * @param string|Message $messageOrName
     * @param array $payload
     */
    public function dispatch($messageOrName, array $payload = []): void
    {
        $this->assertBootstrapped(__METHOD__);

        if(is_string($messageOrName)) {
            $messageOrName = $this->messageFactory()->createMessageFromArray($messageOrName, ['payload' => $payload]);
        }

        if(!$messageOrName instanceof Message) {
            throw new \InvalidArgumentException('Invalid message received. Must be either a known message name or an instance of prooph message. Got '
                . (is_object($messageOrName)? get_class($messageOrName):gettype($messageOrName)));
        }

        switch ($messageOrName->messageType()) {
            case Message::TYPE_COMMAND:
                $preProcessors = $this->commandPreProcessors[$messageOrName->messageName()] ?? [];

                foreach ($preProcessors as $preProcessorOrStr) {
                    if(is_string($preProcessorOrStr)) {
                        $preProcessorOrStr = $this->container->get($preProcessorOrStr);
                    }

                    if(!$preProcessorOrStr instanceof CommandPreProcessor) {
                        throw new \RuntimeException("PreProcessor should be an instance of " . CommandPreProcessor::class . ". Got " . get_class($preProcessorOrStr));
                    }

                    $messageOrName = $preProcessorOrStr->preProcess($messageOrName);
                }

                $this->container->get(self::SERVICE_ID_COMMAND_BUS)->dispatch($messageOrName);
                break;
            case Message::TYPE_EVENT:
                $this->container->get(self::SERVICE_ID_EVENT_BUS)->dispatch($messageOrName);
                break;
            default:
                throw new \RuntimeException("Unsupported message type: " . $messageOrName->messageType());
        }
    }

    public function loadAggregateState(string $aggregateType, string $aggregateId)
    {
        $this->assertBootstrapped(__METHOD__);

        if(!array_key_exists($aggregateType, $this->aggregateDescriptions)) {
            throw new \InvalidArgumentException('Unknown aggregate type: ' . $aggregateType);
        }

        $aggregateDesc = $this->aggregateDescriptions[$aggregateType];

        $snapshotStore = null;

        if($this->container->has(self::SERVICE_ID_SNAPSHOT_STORE)) {
            $snapshotStore = $this->container->get(self::SERVICE_ID_SNAPSHOT_STORE);
        }

        $arRepository = new AggregateRepository(
            $this->container->get(self::SERVICE_ID_EVENT_STORE),
            AggregateType::fromString($aggregateType),
            new ClosureAggregateTranslator($aggregateId, $aggregateDesc['eventApplyMap']),
            $snapshotStore
        );

        /** @var GenericAggregateRoot $aggregate */
        $aggregate = $arRepository->getAggregateRoot($aggregateId);

        if(!$aggregate) {
            throw AggregateNotFound::with($aggregateType, $aggregateId);
        }

        return $aggregate->currentState();
    }

    public function runProjections(bool $keepRunning = true, array $projectionOptions = null): void
    {
        $this->assertBootstrapped(__METHOD__);

        if(null === $this->projectionRunner) {
            $this->projectionRunner = new ProjectionRunner(
                $this->container->get(self::SERVICE_ID_PROJECTION_MANAGER),
                $this->compiledProjectionDescriptions,
                $this
            );
        }

        $this->projectionRunner->run($keepRunning, $projectionOptions);
    }

    public function appVersion(): string
    {
        return $this->appVersion;
    }

    public function loadProjector(string $projectorServiceId): Projector
    {
        return $this->container->get($projectorServiceId);
    }

    public function compileCacheableConfig(): array
    {
        $this->assertInitialized(__METHOD__);

        $assertClosure = function($val) {
            if($val instanceof \Closure) {
                throw new \RuntimeException("At least one EventMachineDescription contains a Closure and is therefor not cacheable!");
            }
        };

        array_walk_recursive($this->compiledCommandRouting, $assertClosure);
        array_walk_recursive($this->aggregateDescriptions, $assertClosure);
        array_walk_recursive($this->eventRouting, $assertClosure);
        array_walk_recursive($this->projectionMap, $assertClosure);
        array_walk_recursive($this->compiledQueryDescriptions, $assertClosure);

        return [
            'commandMap' => $this->commandMap,
            'eventMap' => $this->eventMap,
            'compiledCommandRouting' => $this->compiledCommandRouting,
            'aggregateDescriptions' => $this->aggregateDescriptions,
            'eventRouting' => $this->eventRouting,
            'compiledProjectionDescriptions' => $this->compiledProjectionDescriptions,
            'compiledQueryDescriptions' => $this->compiledQueryDescriptions,
            'queryMap' => $this->queryMap,
            'schemaTypes' => $this->schemaTypes,
            'appVersion' => $this->appVersion,
        ];
    }

    public function messageFactory(): GenericJsonSchemaMessageFactory
    {
        $this->assertInitialized(__METHOD__);

        if(null === $this->messageFactory) {
            $this->messageFactory = new GenericJsonSchemaMessageFactory(
                $this->commandMap,
                $this->eventMap,
                $this->container->get(self::SERVICE_ID_JSON_SCHEMA_ASSERTION)
            );
        }

        return $this->messageFactory;
    }

    public function jsonSchemaAssertion(): JsonSchemaAssertion
    {
        if(null === $this->jsonSchemaAssertion) {
            $this->jsonSchemaAssertion = new class($this->schemaTypes) implements JsonSchemaAssertion {
                private $jsonSchemaAssertion;
                private $types;
                public function __construct(array &$types)
                {
                    $this->jsonSchemaAssertion = new JustinRainbowJsonSchemaAssertion();
                    $this->types = &$types;
                }

                public function assert(string $objectName, array $data, array $jsonSchema)
                {
                    $jsonSchema['definitions'] = array_merge($jsonSchema['definitions'] ?? [], $this->types);

                    $this->jsonSchemaAssertion->assert($objectName, $data, $jsonSchema);
                }
            };
        }

        return $this->jsonSchemaAssertion;
    }

    public function httpMessageBox(): MessageBox
    {
        $this->assertBootstrapped(__METHOD__);

        if(null === $this->httpMessageBox) {
            $this->httpMessageBox = new MessageBox($this);
        }

        return $this->httpMessageBox;
    }

    public function messageSchemas(): array
    {
        $this->assertInitialized(__METHOD__);

        return [
            'commands' => $this->commandMap,
            'events' => $this->eventMap
        ];
    }

    public function bootstrapInTestMode(array $history, array $serviceMap = []): self
    {
        $this->assertInitialized(__METHOD__);
        $this->assertNotBootstrapped(__METHOD__);

        $this->container = new ContainerChain(new TestEnvContainer($serviceMap), $this->container);

        /** @var ActionEventEmitterEventStore $es */
        $es = $this->container->get(self::SERVICE_ID_EVENT_STORE);

        $history = AggregateTestHistoryEventEnricher::enrichHistory($history, $this->aggregateDescriptions);

        $es->appendTo(new StreamName('event_stream'), new \ArrayIterator($history));

        $es->attach(
            ActionEventEmitterEventStore::EVENT_APPEND_TO,
            function (ActionEvent $event): void {
                $recordedEvents = $event->getParam('streamEvents', new \ArrayIterator());
                $this->testSessionEvents = array_merge($this->testSessionEvents, iterator_to_array($recordedEvents));
            }
        );

        $es->attach(
            ActionEventEmitterEventStore::EVENT_CREATE,
            function (ActionEvent $event): void {
                $stream = $event->getParam('stream');
                $recordedEvents = $stream->streamEvents();
                $this->testSessionEvents = array_merge($this->testSessionEvents, iterator_to_array($recordedEvents));
            }
        );

        $this->testMode = true;

        $this->bootstrap();

        return $this;
    }

    public function popRecordedEventsOfTestSession(): array
    {
        $this->assertTestMode(__METHOD__);

        $recordedEvents = $this->testSessionEvents;

        $this->testSessionEvents = [];

        return $recordedEvents;
    }

    private function determineAggregateAndRoutingDescriptions(): void
    {
        $aggregateDescriptions = [];

        $this->compiledCommandRouting = [];

        foreach ($this->commandRouting as $commandName => $commandProcessorDesc) {
            $descArr = $commandProcessorDesc();

            if($descArr['createAggregate']) {
                $aggregateDescriptions[$descArr['aggregateType']] = [
                    'aggregateType' => $descArr['aggregateType'],
                    'aggregateIdentifier' => $descArr['aggregateIdentifier'],
                    'eventApplyMap' => $descArr['eventRecorderMap']
                ];
            }

            $this->compiledCommandRouting[$commandName] = $descArr;
        }

        foreach ($this->compiledCommandRouting as $commandName => &$descArr) {
            $aggregateDesc = $aggregateDescriptions[$descArr['aggregateType']] ?? null;

            if(null === $aggregateDesc) {
                throw new \RuntimeException("Missing aggregate handle method that creates the aggregate of type: " . $descArr['aggregateType']);
            }

            $descArr['aggregateIdentifier'] = $aggregateDesc['aggregateIdentifier'];

            $aggregateDesc['eventApplyMap'] = array_merge($aggregateDesc['eventApplyMap'], $descArr['eventRecorderMap']);
            $aggregateDescriptions[$descArr['aggregateType']] = $aggregateDesc;
        }

        $this->aggregateDescriptions = $aggregateDescriptions;
    }

    private function compileProjectionDescriptions(): void
    {
        foreach ($this->projectionMap as $prjName => $projectionDesc) {
            $this->compiledProjectionDescriptions[$prjName] = $projectionDesc();
        }
    }

    private function compileQueryDescriptions(): void
    {
        foreach ($this->queryDescriptions as $name => $description) {
            $this->compiledQueryDescriptions[$name] = $description();
        }
    }

    private function attachRouterToCommandBus(): void
    {
        /** @var CommandBus $commandBus */
        $commandBus = $this->container->get(self::SERVICE_ID_COMMAND_BUS);
        $snapshotStore = null;

        if($this->container->has(self::SERVICE_ID_SNAPSHOT_STORE)) {
            $snapshotStore = $this->container->get(self::SERVICE_ID_SNAPSHOT_STORE);
        }

        $router = new CommandToProcessorRouter(
            $this->compiledCommandRouting,
            $this->aggregateDescriptions,
            $this->container->get(self::SERVICE_ID_MESSAGE_FACTORY),
            $this->container->get(self::SERVICE_ID_EVENT_STORE),
            $snapshotStore
        );

        $router->attachToMessageBus($commandBus);
    }

    private function setUpEventBus(): void
    {
        $eventRouter = new EventRouter($this->eventRouting);

        if($this->container->has(self::SERVICE_ID_ASYNC_EVENT_PRODUCER)) {
            $eventProducer = $this->container->get(self::SERVICE_ID_ASYNC_EVENT_PRODUCER);

            $eventRouter = new AsyncSwitchMessageRouter(
                $eventRouter,
                $eventProducer
            );
        }

        $eventBus = $this->container->get(self::SERVICE_ID_EVENT_BUS);

        $eventRouter->attachToMessageBus($eventBus);

        $serviceLocatorPlugin = new ServiceLocatorPlugin($this->container);

        $serviceLocatorPlugin->attachToMessageBus($eventBus);
    }

    private function attachEventPublisherToEventStore(): void
    {
        $eventPublisher = new EventPublisher($this->container->get(self::SERVICE_ID_EVENT_BUS));
        $eventStore = $this->container->get(self::SERVICE_ID_EVENT_STORE);

        $eventPublisher->attachToEventStore($eventStore);

        if($eventStore instanceof TransactionalActionEventEmitterEventStore) {
            $transactionManager = new TransactionManager($eventStore);
            $commandBus = $this->container->get(self::SERVICE_ID_COMMAND_BUS);
            $transactionManager->attachToMessageBus($commandBus);
        }
    }

    private function assertNotInitialized(string $method)
    {
        if($this->initialized) {
            throw new \BadMethodCallException("Method $method cannot be called after event machine is initialized");
        }
    }

    private function assertInitialized(string $method)
    {
        if(!$this->initialized) {
            throw new \BadMethodCallException("Method $method cannot be called before event machine is initialized");
        }
    }

    private function assertNotBootstrapped(string $method)
    {
        if($this->bootstrapped) {
            throw new \BadMethodCallException("Method $method cannot be called after event machine is bootstrapped");
        }
    }

    private function assertBootstrapped(string $method)
    {
        if(!$this->bootstrapped) {
            throw new \BadMethodCallException("Method $method cannot be called before event machine is bootstrapped");
        }
    }

    private function assertTestMode(string $method)
    {
        if(!$this->testMode) {
            throw new \BadMethodCallException("Method $method cannot be called if event machine is not bootstrapped in test mode");
        }
    }
}
