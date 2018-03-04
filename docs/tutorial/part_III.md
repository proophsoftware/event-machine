# Part III - Aggregate State

In part II we took a closer look at pure aggregate functions (implemented as static class methods in PHP because of missing function autoloading capabilities).
Pure functions don't have side effects and are stateless. This makes them easy to test and understand.
But an aggregate without state? How can an aggregate protect invariants (its main purpose) without state?

The aggregate needs a way "to look back". It needs to know what happened in the past
according to its own lifecycle. Without its current state and without information about past changes the aggregate could
only execute business logic and enforce business rules based on the given information of the current command passed to a handling function.
In most cases this is not enough.

The functional programming solution to that problem is to pass the current state (which is computed from the past events recorded by the aggregate)
to each command handling function (except the one handling the first command). This means that aggregate **behaviour** (command handling functions)
and aggregate **state** (a data structure of a certain type) are two different things and separated from each other.
How this this is implemented in Event Machine is shown in this part of the tutorial.

## Applying Domain Events

Aggregate state is computed by iterating over all recorded domain events of the aggregate history starting with the oldest event.
Event Machine does not provide a generic way to compute current state, instead the aggregate should have an apply function
for each recorded event. Those apply functions are often prefixed with *when* followed by the event name.

Let's add such a function for our `BuildingAdded` domain event.

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

    public static function whenBuildingAdded(Message $buildingAdded): Building\State
    {
        //@TODO: Return new state for the aggregate
    }
}
```
`BuildingAdded` communicates that a new lifecycle of a building was started (new building was added to our system), so the
`Building::whenBuilidngAdded()` function has to return a new state object and does not receive a current state object
as an argument (next when* function will receive one!).

But what does the `State` object look like? Well, you can use whatever you want. Event Machine does not care about a particular
implementation (see docs for details). However, Event Machine ships with a default implementation of an `ImmutableRecord`.
We use that implementation in the tutorial, but it is your choice if you want to use it in your application, too.

Create a `State` class in `src/Model/Building` (new directory):

```php
<?php
declare(strict_types=1);

namespace App\Model\Building;

use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\Data\ImmutableRecordLogic;

final class State implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var string
     */
    private $buildingId;

    /**
     * @var string
     */
    private $name;

    /**
     * @return string
     */
    public function buildingId(): string
    {
        return $this->buildingId;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }
}

```
*Note: You can use PHPStorm to generate the Getter-Methods. You only have to write the private properties and add the doc blocks with @var type hints.
Then use PHPStorm's ability to add the Getter-Methods (ALT+EINF). By default PHPStorm sets a `get*` prefix for each method. However, immutable records don't
have setter methods and don't work with the `get*` prefix. Just change the template in your PHPStorm config: Settings -> Editor -> File and Code Templates -> PHP Getter Method to:*

```
/**
 * @return ${TYPE_HINT}
 */
public ${STATIC} function ${FIELD_NAME}()#if(${RETURN_TYPE}): ${RETURN_TYPE}#else#end
{
#if (${STATIC} == "static")
    return self::$${FIELD_NAME};
#else
    return $this->${FIELD_NAME};
#end
}
```
Now we can return a new `Building\State` from `Building::whenBuilidngAdded()`.

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

    public static function whenBuildingAdded(Message $buildingAdded): Building\State
    {
        return Building\State::fromArray($buildingAdded->payload());
    }
}

```

Finally, we have to tell Event Machine about that apply function to complete the `AddBuilding` use case description.
In `src/Api/Aggregate`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Model\Building;
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
            ->recordThat(Event::BUILDING_ADDED)
            //Map recorded event to apply function
            ->apply([Building::class, 'whenBuildingAdded']);
    }
}

```
We're done with the write model for the first use case. If you send the `AddBuilding` command again using your GraphQL
client:

```graphql
mutation {
  AddBuilding(
    buildingId:"122a63bf-7388-4cc0-b615-c5cc857a9adc",
    name:"Acme Headquarters"
  )
}
```

... you should receive the following response:

```json
{
  "data": {
    "AddBuilding": true
  }
}
```
Event Machine emphasizes a CQRS and Event Sourcing architecture. For commands this means that no data is returned.
The write model has received and processed the command `AddBuilding` successfully but we don't know what the new
application state looks like. We will use a query, which is the third message type, to get this data.
Head over to tutorial part IV to learn more about queries and application state management using projections.

