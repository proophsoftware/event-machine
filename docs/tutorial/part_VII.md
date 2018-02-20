# Part VII - The Unhappy Path

Developers tend to work out the happy path of a feature only and throw exceptions in every unknown situation.
Often this is caused by bad project management. Developers get domain knowledge from Jira tickets written by a PO
(Jira is used here as a synonym for any ticket system used in an agile process) 
instead of talking to domain experts face-to-face. Most tickets don't include unhappy paths until they happen and find
their way back to the developer as a bug ticket.

Is this really the best way to deal with unexpected scenarios? Wouldn't it be better to prepare for 
the unhappy paths as well? Sure, it takes more time upfront but saves a lot of time later when the application runs
in production and can deal with failure scenarios in a sane way.

Our `Building` aggregate does a bad job with regards to failure handling. Imagine a user is already in a building and tries
to check in again. What does that mean in the real world? First of all it is not possible to be in and out of a building
at the same time. So either a hacker has stolen the identity or system state is broken for whatever reason.
The decision if entrance to the building is blocked or not should be made by the business. And regardless of
the decision it is always interesting to have an event in the event stream about the double check in. This makes monitoring
much simpler than scanning error logs.

We've talked to the domain experts and they want us to notify security in case of a `DoubleCheckIn`. With Event Machine
this is as simple as throwing an exception ;)

We need an event to record a `DoubleCheckIn`:

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
    const USER_CHECKED_IN = 'UserCheckedIn';
    const DOUBLE_CHECK_IN_DETECTED = 'DoubleCheckInDetected';

    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
        /* ... */

        $eventMachine->registerEvent(
            self::DOUBLE_CHECK_IN_DETECTED,
            JsonSchema::object([
                Payload::BUILDING_ID => Schema::buildingId(),
                Payload::NAME => Schema::username(),
            ])
        );
    }
}

```

Now that we have the event we can replace the exception and yield a `DoubleCheckInDetected` event instead:

```php
<?php

declare(strict_types=1);

namespace App\Model;

use App\Api\Event;
use App\Api\Payload;
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

    public static function checkInUser(Building\State $state, Message $checkInUser): \Generator
    {
        if($state->isUserCheckedIn($checkInUser->get(Payload::NAME))) {
            yield [Event::DOUBLE_CHECK_IN_DETECTED, $checkInUser->payload()];
            return; //<-- Note: we need to return, otherwise UserCheckedIn would be yielded, too
        }

        yield [Event::USER_CHECKED_IN, $checkInUser->payload()];
    }

    public static function whenUserCheckedIn(Building\State $state, Message $userCheckedIn): Building\State
    {
        return $state->withCheckedInUser($userCheckedIn->get(Payload::NAME));
    }
    
    public static function whenDoubleCheckInDetected(Building\State $state, Message $event): Building\State
    {
        //No state change required, simply return current state
        return $state;
    }
}

```

We need to tell Event Machine that `Building::checkInUser()` yields `DoubleCheckInDetected` in some situations:

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
        /* ... */

        $eventMachine->process(Command::CHECK_IN_USER)
            ->withExisting(self::BUILDING)
            ->handle([Building::class, 'checkInUser'])
            ->recordThat(Event::USER_CHECKED_IN)
            ->apply([Building::class, 'whenUserCheckedIn'])
            ->orRecordThat(Event::DOUBLE_CHECK_IN_DETECTED)
            ->apply([Building::class, 'whenDoubleCheckInDetected']);
    }
}

```

Try to check in *John* again:

```graphql
mutation{
  CheckInUser(
    buildingId:"122a63bf-7388-4cc0-b615-c5cc857a9adc", 
    name:"John"
  )
}
```

Instead of an error response we get back a success response:

```json
{
  "data": {
    "CheckInUser": true
  }
}
```

But when we look at the event stream (table `_4228e4a00331b5d5e751db0481828e22a2c3c8ef`) we see a `DoubleCheckInDetected` event.

no | event_id | event_name | payload | metadata | created_at
---|-----------|------------|--------|--------|---------
1 | bce42506-...| BuildingAdded | {"buildingId":"122a63bf-...","name":"Acme Headquarters"} | {"_aggregate_id": "122a63bf-...", "_causation_id": "e482f5b8-...", "_aggregate_type": "Building", "_causation_name": "AddBuilding", "_aggregate_version": 1} | 2018-02-14 22:09:32.039848
2 | 0ee8d2fb-...| UserCheckedIn | {"buildingId":"122a63bf-...","name":"John"} | {"_aggregate_id": "122a63bf-...", "_causation_id": "1ce0e46d-...", "_aggregate_type": "Building", "_causation_name": "CheckInUser", "_aggregate_version": 2} | 2018-02-16 21:37:55.131122
3 | 4f6a8429-...| DoubleCheckInDetected | {"buildingId":"122a63bf-...","name":"John"} | {"_aggregate_id": "122a63bf-...", "_causation_id": "c347dd85-...", "_aggregate_type": "Building", "_causation_name": "CheckInUser", "_aggregate_version": 3} | 2018-02-16 23:03:59.739666

## Process Manager 

To complete the user story we have to notify security. The security team uses a dedicated monitoring application that
can receive arbitrary notification messages. To communicate with that external system we can use a so called **process manager** or
**policy**. Maybe you're more familiar with the term event listener but be careful to not mix it with event listeners known
from web frameworks like Symfony or Zend. Listeners in Event Machine **react** on domain events and trigger follow up
commands, send emails or interact with external systems.

We can simulate the security monitoring system with a small JS app shipped with the event-machine-skeleton.
Open `http://localhost:8080/ws.html` in your browser.

*Note: If the app shows a connection error then try to log into the rabbit mgmt console first: `http://localhost:8081`. Accept the self-signed certificate
and login with usr: `prooph` pwd: `prooph`. If you're locked in switch back to `http://localhost:8080/ws.html` and reload the page.*

If the app says `Status: Connected to websocket: ui-queue` it is ready to receive messages from Event Machine.

In `src/Service/ServiceFactory` you can find a factory method for a `App\Infrastructure\ServiceBus\UiExchange`.
It's a default domain event listener shipped with the skeleton that can be used to push events on a *RabbitMQ ui-exchange*.
The exchange is preconfigured (you can see that in the rabbit mgmt UI) and the JS app connects to a corresponding *ui-queue*.

In `src/Api/Listener` we can put together the pieces:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Infrastructure\ServiceBus\UiExchange;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;

class Listener implements EventMachineDescription
{

    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->on(Event::DOUBLE_CHECK_IN_DETECTED, UiExchange::class);
    }
}

```

Whenever a `DoubleCheckInDetected` event is recorded and written to the stream Event Machine invokes the `UiExchange`
listener that takes the event and pushes it to *rabbit*.

And again try to check in *John* while keeping an eye on the monitoring app `http://localhost:8080/ws.html`:

```graphql
mutation{
  CheckInUser(
    buildingId:"122a63bf-7388-4cc0-b615-c5cc857a9adc", 
    name:"John"
  )
}
```
![Monitoring UI](img/monitoring.png)]
  
## The End

Congratulations! You've mastered the Event Machine tutorial. There are two bonus parts available to learn more
about **custom projections** and **testing with Event Machine**. 
The current implementation is available as a **demo** branch of `proophsoftware/event-machine-skeleton`. 
There is a second branch called **demo-oop** available that contains a similar
implementation. But the `Building` aggregate is designed using an object oriented approach rather than
the functional approach shown in the tutorial. If you like that OOP style more you can of course use that.

Functional programming fans might dislike the static class methods. You can also use real functions instead of static class methods but
you have to configure *composer* to always require the files containing your functions. It's up to you.

The Event Machine API docs contain a lot more details and last but not least a reminder that the prooph software team
offers commercial project support and workshops for Event Machine and the prooph components.

Our workshops include Event Storming sessions and how to turn the results into working prototypes using Event Machine.
We can also show and discuss framework integrations. Event Machine can easily be integrated with *Symfony*, *Laravel* and
other PHP web frameworks. The skeleton is based on *Zend Expressive* so you can handle http related stuff (like authentication)
using *PSR-15* middlewares. But again, other web frameworks play nice together with Event Machine, too.

[![prooph software](https://github.com/codeliner/php-ddd-cargo-sample/raw/master/docs/assets/prooph-software-logo.png)](http://prooph.de)

If you are interested please [get in touch](http://getprooph.org/#get-in-touch)! 



