# Part II - The Building Aggregate

In Event Machine we can take a short cut and skip command handlers.
This is possible because `Aggregates` in Event Machine are **stateless** and **pure**. This means that
they don't have internal **state** and also **no dependencies**. 

*Simply put: they are just functions*

Event Machine can take over the boilerplate and we as developers can **focus on the business logic**. I'll explain
more details later. First we want to see a **pure aggregate function** in action.

*Note: If you've worked with a CQRS framework before this is maybe confusing
because normally a command is handled by a command handler (comparable to an application service that handles a domain action)
and the command handler would load a business entity or "DDD" aggregate from a repository. We still use the aggregate concept but make
use of a functional programming approach. It keeps the domain model lean and testable and allows some nice
optimizations for a RAD infrastructure.* 

Let's add the first aggregate called `Building` in a new `Model` folder:

```php
<?php

declare(strict_types=1);

namespace App\Model;

use Prooph\EventMachine\Messaging\Message;

final class Building
{
    public static function add(Message $addBuilding): \Generator
    {
        //yield domain events
    }
}

```

As you can see the `Building` class uses static methods. It does not extend from a base class and has no dependencies.
We could also use plain PHP functions instead but unfortunately PHP does not provide function autoloading (yet), so
we stick to static methods and group all methods of an aggregate in a class.

`Building::add()` receives `AddBuilding` messages (of type command) and should perform the business logic needed to
add a new building to our application. But instead of adding a new building directly we want to yield a domain event.

## Domain Events

Domain events are the second message type used by Event Machine. The domain model is event sourced. Hence, it records
all state changes in a series of domain events. Domain events are yielded by aggregate methods and stored in an event store
managed by Event Machine. The series of events can then be used to calculate the current state of an aggregate.
We will see that in action in a later part of the tutorial and get a better understanding of the technique 
when we add more use cases to the application.

For now let's add the first domain event in `src/Api/Event`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;

class Event implements EventMachineDescription
{
    const BUILDING_ADDED = 'BuildingAdded';

    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->registerEvent(
            self::BUILDING_ADDED,
            JsonSchema::object(
                [
                    'buildingId' => JsonSchema::uuid(),
                    'name' => JsonSchema::string(['minLength' => 2])
                ]
            )
        );
    }
}

``` 
It looks similar to the `AddBuilding` command but uses a name in past tense. That is a very important difference.
Commands **tell** the application what it should do and events **represent facts** that have happened.

## Yielding Events

Aggregate methods can yield null, one or multiple domain events depending on the result of the executed business logic.
If an aggregate method yields `null` it indicates that no important fact happened and no event needs to be recorded.
In many cases an aggregate method will yield one event which is the fact caused by the corresponding command.
But there is no one-to-one connection between commands and events. In some cases more than one event is needed to communicate
important facts or an error event is yielded instead of the expected event (we'll see that later).

For the first use case we simply yield a `BuildingAdded` domain event when `Building::add()` is called with a `AddBuilding`
command.

```php
<?php

declare(strict_types=1);

namespace App\Model;

use App\Api\Event;
use Prooph\EventMachine\Messaging\Message;

final class Building
{
    public static function add(Message $addBuilding): \Generator
    {
        yield [Event::BUILDING_ADDED, $addBuilding->payload()];
    }
}

```
The special array syntax for yielding events is a short cut used by Event Machine. It creates the event based on given
event name and payload and stores it in the event stream.

## Aggregate Description

If we switch back to the GraphQL client and send the `AddBuilding` command again, Event Machine still
complains about a missing command handler. We need to tell Event Machine about our new aggregate and that it is 
responsible for handling `AddBuilding` commands. We can do this in another Event Machine Description in `src/Api/Aggregate`.

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;

class Aggregate implements EventMachineDescription
{
    const BUILDING = 'Building';

    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->process(Command::ADD_BUILDING)
            ->withNew(self::BUILDING)
            ->identifiedBy('buildingId')
            ->handle([Building::class, 'add'])
            ->recordThat(Event::BUILDING_ADDED);
    }
}

```
The connection between command and aggregate is described in a very verbose and readable way. Our IDE can suggest the
describing methods of Event Machine's fluent interface and it is easy to remember each step.

- `process` tells Event Machine that the following description is for the given command name.
- `withNew/withExisting` tells Event Machine which aggregate handles the command and if the aggregate exists already or a new one should be created.
- `identifiedBy` tells Event Machine which message payload property should be used to identify the responsible aggregate. Every command send to the aggregate and 
every event yielded by the aggregate should contain this property
- `handle` takes a callable as argument which is the aggregate method responsible for handling the command defined in `process`. We use the callable array syntax of PHP
which can be analyzed by modern IDEs like PHPStorm for auto completion and refactorings.
- `recordThat` tells event machine which event is yielded by the aggregate's command handling method.   

If we try again to send the GraphQL `AddBuilding` command we get a new error:

```json
{
  "errors": [
    {
      "debugMessage": "No apply function specified for event: BuildingAdded",
      "message": "Internal server error",
      "category": "internal"
    }
  ],
  "data": []
}
```

Command handling works now but an apply function is missing. In part III of the tutorial you'll learn how to add such a function and why it is needed.


