<?php
declare(strict_types = 1);

namespace Prooph\EventMachine;

use Interop\Http\Middleware\ServerMiddlewareInterface;
use Prooph\Common\Event\ActionEvent;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Commanding\CommandProcessorDescription;
use Prooph\EventMachine\Commanding\CommandToProcessorRouter;
use Prooph\EventMachine\Container\ContainerChain;
use Prooph\EventMachine\Container\FactoriesContainer;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Prooph\EventMachine\Messaging\GenericJsonSchemaMessageFactory;
use Prooph\EventStore\EventStore;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\MessageBus;
use Prooph\SnapshotStore\SnapshotStore;
use Psr\Container\ContainerInterface;

final class EventMachine
{
    const DEP_EVENT_STORE = 'eventStore';
    const DEP_COMMAND_BUS = 'commandBus';
    const DEP_EVENT_BUS = 'eventBus';

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
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var bool
     */
    private $bootstrapped = false;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    public function __construct(ContainerInterface $applicationContainer = null)
    {
        $container = new FactoriesContainer();

        if(null !== $applicationContainer) {
            $container = new ContainerChain($applicationContainer, $container);
        }

        $this->container = $container;
    }

    public function load(string $description): void
    {
        $this->assertNotBootstrapped(__METHOD__);
        call_user_func([$description, 'describe'], $this);
    }

    public function registerCommand(string $commandName, $schemaOrPath): self
    {
        $this->assertNotBootstrapped(__METHOD__);
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
        $this->assertNotBootstrapped(__METHOD__);

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
        $this->assertNotBootstrapped(__METHOD__);
        if(array_key_exists($commandName, $this->commandRouting)) {
            throw new \BadMethodCallException("Method process was called twice for the same command: " . $commandName);
        }

        if(!array_key_exists($commandName, $this->commandMap)) {
            throw new \BadMethodCallException("Command $commandName is unknown. You should register it first.");
        }

        $this->commandRouting[$commandName] = new CommandProcessorDescription($commandName, $this);

        return $this->commandRouting[$commandName];
    }

    public function isKnownEvent(string $eventName): bool
    {
        return array_key_exists($eventName, $this->eventMap);
    }

    public function bootstrap(): self
    {
        $this->assertNotBootstrapped(__METHOD__);

        $this->determineAggregateDescriptions();
        $this->attachRouterToCommandBus();


        $this->bootstrapped = true;

        return $this;
    }

    public function httpMessageBox(): ServerMiddlewareInterface
    {
        $this->assertBootstrapped(__METHOD__);


    }

    public function commandRouting(): array
    {
        if(null === $this->compiledCommandRouting) {
            $this->determineAggregateDescriptions();
        }

        return $this->compiledCommandRouting;
    }

    public function aggregateDescriptions(): array
    {
        if(null === $this->aggregateDescriptions) {
            $this->determineAggregateDescriptions();
        }

        return $this->aggregateDescriptions;
    }

    private function determineAggregateDescriptions(): void
    {
        $aggregateDescriptions = [];

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
        $commandBus = $this->container->get(CommandBus::class);
        $eventStore = $this->container->get(EventStore::class);
        $snapshotStore = null;

        if($this->container->has(SnapshotStore::class)) {
            $snapshotStore = $this->container->get(SnapshotStore::class);
        }

        $router = new CommandToProcessorRouter(
            $this->commandRouting,
            $this->aggregateDescriptions,
            $this->getMessageFactory(),
            $eventStore,
            $snapshotStore
        );

        $router->attachToMessageBus($commandBus);
    }

    private function getMessageFactory(): GenericJsonSchemaMessageFactory
    {
        if(null === $this->messageFactory) {
            $this->messageFactory = new GenericJsonSchemaMessageFactory(
                $this->commandMap,
                $this->eventMap,
                $this->container->get(JsonSchemaAssertion::class)
            );
        }

        return $this->messageFactory;
    }

    private function assertNotBootstrapped(string $method)
    {
        if($this->bootstrapped) {
            throw new \BadMethodCallException("Method $method cannot be called after event machine is bootstrapped");
        }
    }

    private function assertBootstrapped(string $method)
    {
        if($this->bootstrapped) {
            throw new \BadMethodCallException("Method $method cannot be called before event machine is bootstrapped");
        }
    }
}
