<?php
declare(strict_types = 1);

namespace Prooph\EventMachine;

use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Commanding\CommandProcessorDescription;
use Prooph\EventMachine\Commanding\CommandToProcessorRouter;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Prooph\EventMachine\JsonSchema\WebmozartJsonSchemaAssertion;
use Prooph\EventMachine\Messaging\GenericJsonSchemaMessageFactory;
use Prooph\EventStoreBusBridge\EventPublisher;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\Plugin\Router\AsyncSwitchMessageRouter;
use Prooph\ServiceBus\Plugin\Router\EventRouter;
use Psr\Container\ContainerInterface;

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

    /**
     * Map of command names and corresponding json schema of payload
     *
     * Json schema can be passed as array or path to schema file
     *
     * @var array
     */
    private $commandMap = [];

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

    /**
     * @var bool
     */
    private $initialized = false;

    private $bootstrapped = false;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var JsonSchemaAssertion
     */
    private $jsonSchemaAssertion;

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
        $this->attachRouterToEventBus();
        $this->attachEventPublisherToEventStore();

        $this->bootstrapped = true;

        return $this;
    }

    public function dispatch(Message $message): void
    {
        $this->assertBootstrapped(__METHOD__);

        switch ($message->messageType()) {
            case Message::TYPE_COMMAND:
                $this->container->get(self::SERVICE_ID_COMMAND_BUS)->dispatch($message);
                break;
            case Message::TYPE_EVENT:
                $this->container->get(self::SERVICE_ID_EVENT_BUS)->dispatch($message);
                break;
            default:
                throw new \RuntimeException("Unsupported message type: " . $message->messageType());
        }
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

        return [
            'commandMap' => $this->commandMap,
            'eventMap' => $this->eventMap,
            'compiledCommandRouting' => $this->compiledCommandRouting,
            'aggregateDescriptions' => $this->aggregateDescriptions
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
            $this->jsonSchemaAssertion = new WebmozartJsonSchemaAssertion();
        }

        return $this->jsonSchemaAssertion;
    }

    public function httpMessageBox(): MiddlewareInterface
    {
        $this->assertBootstrapped(__METHOD__);
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

    private function attachRouterToEventBus(): void
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
    }

    private function attachEventPublisherToEventStore(): void
    {
        $eventPublisher = new EventPublisher($this->container->get(self::SERVICE_ID_EVENT_BUS));
        $eventStore = $this->container->get(self::SERVICE_ID_EVENT_STORE);

        $eventPublisher->attachToEventStore($eventStore);
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
}
