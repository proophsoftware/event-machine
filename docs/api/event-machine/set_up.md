# Set Up

Event Machine is not a full stack framework. Instead you integrate it any PHP framework that supports [PHP Standards Recommendations](https://www.php-fig.org/psr/).

## Skeleton

The easiest way to get started is by using the [skeleton](https://github.com/proophsoftware/event-machine-skeleton).
It ships with a preconfigured Event Machine, a recommended project structure and ready-to-use docker containers.

However, the skeleton is not the only way to set up Event Machine. You can tweak set up as needed.

## Required Infrastructure

Event Machine is based on PHP 7.1 or higher. Package dependencies are installed using [composer](https://getcomposer.org/).

### Database

Event Machine uses [prooph/event-store](http://docs.getprooph.org/event-store/) to store **events** recorded by the **write model**
and a [DocumentStore](../document-store/overview.md) to store the **read model**. The skeleton uses prooph's Postgres event store
and a [Postgres Document Store](https://github.com/proophsoftware/postgres-document-store) implementation.
This allows Event Machine to work with a single database, but that's not a requirement. You can mix and match as needed and even use
another storage mechanism for the read model if you only use [custom projections](../projections/custom_projections.md).

#### Creating The Event Stream

All events are stored in a single stream. You cannot change this strategy in Event Machine because projections rely on a guaranteed
order of events. A single stream is the only way to fulfill this requirement. When using a relational database as an event store a single
table is also very efficient. A longer discussion about the topic can be found
in the [prooph/pdo-event-store repo](https://github.com/prooph/pdo-event-store/issues/139).

An easy way to create the needed stream is to use the event store API.

```php
<?php
declare(strict_types=1);

namespace Prooph\EventMachine;

use ArrayIterator;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;

require_once 'vendor/autoload.php';

$container = require 'config/container.php';

/** @var EventStore $eventStore */
$eventStore = $container->get('EventMachine.EventStore');
$eventStore->create(new Stream(new StreamName('event_stream'), new ArrayIterator()));

echo "done.\n";
```

Such a [script](https://github.com/proophsoftware/event-machine-skeleton/blob/master/scripts/create_event_stream.php) is used in the skeleton.
As you can see we request the event store from a container that we get by requiring a config file. The skeleton uses [Zend Strategility](https://github.com/zendframework/zend-stratigility)
and this is a common approach in Strategility and Zend Expressive based applications. Adopt the script according to your framework of choice.
The only thing that really matters is that you get a configured prooph/event-store from the [PSR-11 container](https://www.php-fig.org/psr/psr-11/)
used by Event Machine.

#### Read Model Storage

Read Model storage is set up on the fly. You don't need to prepare it upfront, but you can if you prefer to work with a database migration tool of your choice.
Learn more about read model storage set up in the [projections chapter](../projections).

## Event Machine Descriptions

Event Machine uses a "zero configuration" approach. While you have to configure integrated packages like *prooph/event-store*, Event Machine itself
does not require centralized configuration. Instead it loads so called *Event Machine Descriptions*:

```php
<?php

declare(strict_types=1);

namespace Prooph\EventMachine;

interface EventMachineDescription
{
    public static function describe(EventMachine $eventMachine): void;
}

```

Any class implementing the interface can be loaded by Event Machine. The task of a *Description* is to tell Event Machine how the application is structured.
This is done in a programmatic way using Event Machine's registration API which we will cover in the next chapter.
Here is a simple example of a *Description* that registers a *command* in Event Machine.

```php
<?php
declare(strict_types=1);


namespace App\Api;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;

class Command implements EventMachineDescription
{
    const COMMAND_CONTEXT = 'MyContext.';
    const REGISTER_USER = self::COMMAND_CONTEXT . 'RegisterUser';

    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
         $eventMachine->registerCommand(
            self::REGISTER_USER,  //<-- Name of the  command defined as constant above
            JsonSchema::object([
                Payload::USER_ID => Schema::userId(),
                Payload::USERNAME => Schema::username(),
                Payload::EMAIL => Schema::email(),
            ])
         );

    }
}
```

Now we only need to tell Event Machine that it should load the *Description*:

```php
declare(strict_types=1);

require_once 'vendor/autoload.php';

$eventMachine = new EventMachine();

$eventMachine->load(App\Api\Command::class);

```

## Initialize & Bootstrap

Event Machine is bootstrapped in three phases. *Descriptions* are loaded first, followed by a `$eventMachine->initialize($container, $appVersion)` call.
Finally, `$eventMachine->bootstrap($environment, $debugMode)` prepares the system so that it can handle incoming messages.

Bootstrapping is split because the description and initialization phases can be skipped in production.
During `Description phase` Event Machine is configured. Depending on the size of the application this can result in many method calls
which are known to be slow. During development that's not a problem but in production you don't want to do that on every request.
Between two deployments code does not change and therefor the configuration does not change. We can safely cache it and respond faster to requests.

Before caching of the configuration is possible Event Machine needs to aggregate information from all *Descriptions*.
This is done in the *Initialize phase*. The phase also requires a PSR-11 container that can be used by Event Machine to get third-party services.
See section about dependency injection for details.

The second argument of the `initialize` method is a string representing the application version. It defaults to `0.1.0`. The application version
comes into play when organizing projections. More details can be found in the projections chapter.







