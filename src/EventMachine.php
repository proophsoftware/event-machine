<?php
declare(strict_types = 1);

namespace Prooph\EventMachine;

use Interop\Http\ServerMiddleware\MiddlewareInterface;
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
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Prooph\EventMachine\JsonSchema\JustinRainbowJsonSchemaAssertion;
use Prooph\EventMachine\Messaging\GenericJsonSchemaMessageFactory;
use Prooph\EventSourcing\Aggregate\AggregateRepository;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventStore\ActionEventEmitterEventStore;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalActionEventEmitterEventStore;
use Prooph\EventStoreBusBridge\EventPublisher;
use Prooph\EventStoreBusBridge\TransactionManager;
use Prooph\Psr7Middleware\MessageMiddleware;
use Prooph\Psr7Middleware\Response\ResponseStrategy;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\Plugin\Router\AsyncSwitchMessageRouter;
use Prooph\ServiceBus\Plugin\Router\EventRouter;
use Prooph\ServiceBus\Plugin\ServiceLocatorPlugin;
use Prooph\ServiceBus\QueryBus;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

final class EventMachine
{
    const SERVICE_ID_EVENT_STORE = 'EventMachine.EventStore';
    const SERVICE_ID_SNAPSHOT_STORE = 'EventMachine.SnapshotStore';
    const SERVICE_ID_COMMAND_BUS = 'EventMachine.CommandBus';
    const SERVICE_ID_EVENT_BUS = 'EventMachine.EventBus';
    const SERVICE_ID_QUERY_BUS = 'EventMachine.QueryBus';
    const SERVICE_ID_ASYNC_EVENT_PRODUCER = 'EventMachine.AsyncEventProducer';
    const SERVICE_ID_MESSAGE_FACTORY = 'EventMachine.MessageFactory';
    const SERVICE_ID_JSON_SCHEMA_ASSERTION = 'EventMachine.JsonSchemaAssertion';
    const SERVICE_ID_HTTP_MESSAGE_BOX_RESPONSE_STRATEGY = 'EventMachine.HttpMessageBox.ResponseStrategy';

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
     * @var ContainerInterface
     */
    private $container;

    private $initialized = false;

    private $bootstrapped = false;

    private $testMode = false;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var JsonSchemaAssertion
     */
    private $jsonSchemaAssertion;

    /**
     * @var MiddlewareInterface
     */
    private $httpMessageBox;

    private $testSessionEvents = [];

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

    public function isKnownCommand(string $commandName): bool
    {
        return array_key_exists($commandName, $this->commandMap);
    }

    public function isKnownEvent(string $eventName): bool
    {
        return array_key_exists($eventName, $this->eventMap);
    }

    public function initialize(ContainerInterface $container): self
    {
        $this->assertNotInitialized(__METHOD__);

        $this->determineAggregateAndRoutingDescriptions();

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

        return [
            'commandMap' => $this->commandMap,
            'eventMap' => $this->eventMap,
            'compiledCommandRouting' => $this->compiledCommandRouting,
            'aggregateDescriptions' => $this->aggregateDescriptions,
            'eventRouting' => $this->eventRouting,
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
            $this->jsonSchemaAssertion = new JustinRainbowJsonSchemaAssertion();
        }

        return $this->jsonSchemaAssertion;
    }

    public function httpMessageBox(): MiddlewareInterface
    {
        $this->assertBootstrapped(__METHOD__);

        if($this->container->has(self::SERVICE_ID_QUERY_BUS)) {
            $queryBus = $this->container->get(self::SERVICE_ID_QUERY_BUS);
        } else {
            $queryBus = new QueryBus();
        }

        if(null === $this->httpMessageBox) {
            $this->httpMessageBox = new MessageMiddleware(
                $this->container->get(self::SERVICE_ID_COMMAND_BUS),
                $queryBus,
                $this->container->get(self::SERVICE_ID_EVENT_BUS),
                $this->messageFactory(),
                $this->httpResponseStrategy()
            );
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

        $this->container = new ContainerChain(new TestEnvContainer(), $this->container);

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

    private function httpResponseStrategy(): ResponseStrategy
    {
        return $this->container->has(self::SERVICE_ID_HTTP_MESSAGE_BOX_RESPONSE_STRATEGY)
            ?
            $this->container->get(self::SERVICE_ID_HTTP_MESSAGE_BOX_RESPONSE_STRATEGY)
            :
            new class() implements ResponseStrategy
            {
                public function fromPromise(\React\Promise\PromiseInterface $promise): ResponseInterface
                {
                    $data = null;

                    $promise->done(function($result) use (&$data) {
                         $data = $result;
                     });

                    return new \Zend\Diactoros\Response\JsonResponse($data);
                }

                public function withStatus(int $statusCode): ResponseInterface
                {
                    return new \Zend\Diactoros\Response\JsonResponse([], $statusCode);
                }
            };
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
