# Event Machine Descriptions

In the previous chapter "Set Up" we already learned that Event Machine loads `EventMachineDescription`s and passes itself as the only argument
to a static `describe` method.

```php
<?php

declare(strict_types=1);

namespace Prooph\EventMachine;

interface EventMachineDescription
{
    public static function describe(EventMachine $eventMachine): void;
}

```

Descriptions need to be loaded **before** `EventMachine::initialize()` is called.
In the skeleton descriptions are listed in [config/autoload/global.php](https://github.com/proophsoftware/event-machine-skeleton/blob/master/config/autoload/global.php#L37)
and this list is read by the event machine factory method of the [ServiceFactory](https://github.com/proophsoftware/event-machine-skeleton/blob/master/src/Service/ServiceFactory.php#L268).

```php
public function eventMachine(): EventMachine
{
    $this->assertContainerIsset();

    return $this->makeSingleton(EventMachine::class, function () {
        $eventMachine = new EventMachine();

        //Load descriptions here or add them to config/autoload/global.php
        foreach ($this->config->arrayValue('event_machine.descriptions') as $desc) {
            $eventMachine->load($desc);
        }

        $containerChain = new ContainerChain(
            $this->container,
            new EventMachineContainer($eventMachine)
        );

        $eventMachine->initialize($containerChain);

        return $eventMachine;
    });
}
```

### Organising Descriptions

If you followed the tutorial, you already know that you can avoid code duplication and typing errors with a few simple tricks.
Clever combinations of class and constant names can provide readable code without much effort. The skeleton ships with default Event Machine Descriptions
to support you with that idea. You can find them in [src/Api](https://github.com/proophsoftware/event-machine-skeleton/tree/master/src/Api).

## Registration API

Event Machine provides various `registration` methods. Those methods can only be called during **description phase** (see "Set Up" chapter for details about bootstrap phases).
Here is an overview of available methods along with a short explanation.

| Method | Description |
|---|---|
| EventMachine::registerCommand(string $name, JsonSchema\ObjectType $payloadSchema): EventMachine | Add a command message to the system along with its payload schema |
| EventMachine::registerEvent(string $name, JsonSchema\ObjectType $payloadSchema): EventMachine | Add an event message to the system along with its payload schema |
| EventMachine::registerQuery(string $name, JsonSchema\ObjectType $payloadSchema): QueryDescription | Add a query message to the system along with its payload schema |
| EventMachine::registerType(string $name, JsonSchema\ObjectType $payloadSchema): void | Add a data type to the system along with its json schema |
| EventMachine::registerEnumType(string $name, JsonSchema\EnumType $payloadSchema): void | Add an enum type to the system along with its json schema |
| EventMachine::preProcess(string $cmdName, string \| CommandPreProcessor $preProcessor): EventMachine | Service id or instance of a CommandPreProcessor invoked before command is dispatched |
| EventMachine::process(string $cmdName): CommandProcessorDescription | Describe handling of a command using returned CommandProcessorDescription |
| EventMachine::on(string $eventName, string \| callable $listener): EventMachine | Service id or callable event listener invoked after event is written to event stream |
| EventMachine::watch(Stream $stream): ProjectionDescription | Describe a projection by using returned ProjectionDescription |


### Message Payload Schema

Messages are like HTTP requests. Well, a HTTP request is a message of a specific format. Event Machine messages on the other hand are [prooph/common messages](https://github.com/prooph/common/blob/master/docs/messaging.md).
Like HTTP requests **messages should be validated before doing anything with them**. It can become a time consuming task to write validation logic for each message
by hand. Event Machine would not be a rapid application development framework if it does not ship with a built-in way to validate messages.
Long story; short: [Json Schema Draft 6](http://json-schema.org/specification-links.html#draft-6) is used to describe message payloads and validation rules for payload properties.
You do this using `JsonSchema` wrapper objects provided by Event Machine. Those objects are much simpler to use instead of writing JSON Schema by hand and drastically improve
readability of the code.

Again the command registration example from the previous chapter:

```php
$eventMachine->registerCommand(
    self::REGISTER_USER,
    JsonSchema::object([
        Payload::USER_ID => Schema::userId(),
        Payload::USERNAME => Schema::username(),
        Payload::EMAIL => Schema::email(),
    ])
);
```
This code speaks for itself, doesn't it? It is beautiful and clean (IMHO) and once you're used to it you can add new messages to the system in less than 30 seconds.
The chapter about "Json Schema" covers all the details. Make sure to check it out.

*A nice side effect of this approach is out-of-the-box [Swagger UI](https://swagger.io/tools/swagger-ui/) support. Learn more about it in the "Swagger UI" chapter.*

### Command Registration

Event Machine needs to know which commands can be processed by the system. Therefor, you have to register them before defining processing logic.

*Software developed with Event Machine follows a Command-Query-Responsibility-Segregation (short CQRS) approach.
Commands are used to trigger state changes without returning modified state and queries are used to request current state without modifying it.*

You're ask to tell Event Machine a few details about available commands. Each command should have a **unique name** and a **payload schema**.
It is recommended to add a context as prefix in front of each command name. Let's take an example from the tutorial but add a context to the command name:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;

class Command implements EventMachineDescription
{
    const CMD_CXT = 'BuildingMgmt.';
    const ADD_BUILDING = self::CMD_CXT.'AddBuilding';

    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->registerCommand(
            Command::ADD_BUILDING,
            JsonSchema::object(
                [
                    'buildingId' => JsonSchema::uuid(),
                    'name' => JsonSchema::string()->withMinLength(2)
                ]
            )
        );
    }
}

```


Event Machine makes no assumptions about the format of the name. A common approach is to use a *dot notation* to separate context from message name
e.g. `BuildingMgmt.AddBuilding`. Using *dot notation* has the advantage that message broker like RabbitMQ can use it for routing.

### Command Processing









