<?php
declare(strict_types = 1);

namespace Prooph\EventMachine;

use Interop\Http\Middleware\ServerMiddlewareInterface;
use Prooph\EventMachine\Commanding\CommandProcessorDescription;
use Prooph\EventMachine\Container\ContainerChain;
use Prooph\EventMachine\Container\FactoriesContainer;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Prooph\EventMachine\Messaging\GenericJsonSchemaMessageFactory;
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

    public function __construct(ContainerInterface $applicationContainer = null)
    {
        $container = new FactoriesContainer();

        if(null !== $applicationContainer) {
            $container = new ContainerChain($applicationContainer, $container);
        }

        $this->container = $container;
    }

    public function load(callable $description): void
    {
        $this->assertNotBootstrapped(__METHOD__);
        $description($this);
    }

    public function registerCommand(string $commandName, $schemaOrPath): self
    {
        $this->assertNotBootstrapped(__METHOD__);
        if(array_key_exists($commandName, $this->commandMap)) {
            throw new \RuntimeException("Command $commandName was already registered.");
        }

        if(!is_array($schemaOrPath) || !is_string($schemaOrPath)) {
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

        if(!is_array($schemaOrPath) || !is_string($schemaOrPath)) {
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

        $this->attachRouterToCommandBus();


        $this->bootstrapped = true;

        return $this;
    }

    public function httpMessageBox(): ServerMiddlewareInterface
    {
        $this->assertBootstrapped(__METHOD__);


    }

    private function attachRouterToCommandBus()
    {

    }

    private function getMessageFactory(): GenericJsonSchemaMessageFactory
    {
        return new GenericJsonSchemaMessageFactory(
            $this->commandMap,
            $this->eventMap,
            $this->container->get(JsonSchemaAssertion::class)
        );
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
