# Bonus I - Custom Projection

The product owner comes along with a new feature request. They need a way to look up the building a user is
checked into, if any.

## Exercise

Before we implement that feature you're asked to implement the *check out user* use case.
Add a command `CheckOutUser` and an event `UserCheckedOut`. Let the `Building` aggregate and `Building\State` handle the command
and make sure that `DoubleCheckOutDetected` can also be monitored using the monitoring UI.

Does it work? Great!

## Implement a Projector

What we need is a list of usernames and a reference to the building they are checked into.
A custom projection can keep track of `UserCheckedIn` and `UserCheckedOut` events to keep the list up-to-date.

To do that we need our own `Prooph\EventMachine\Projecting\Projector` implementation. Create a new class called
`UserBuildingList` in `src/Infrastructure/Projector` with the following content:

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Projector;

use App\Api\Event;
use App\Api\Payload;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Persistence\DocumentStore;
use Prooph\EventMachine\Projecting\AggregateProjector;
use Prooph\EventMachine\Projecting\Projector;

final class UserBuildingList implements Projector
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    public function __construct(DocumentStore $documentStore)
    {
        $this->documentStore = $documentStore;
    }

    public function prepareForRun(string $appVersion, string $projectionName): void
    {
        if(!$this->documentStore->hasCollection($this->generateCollectionName($appVersion, $projectionName))) {
            $this->documentStore->addCollection(
                $this->generateCollectionName($appVersion, $projectionName)
                /* Note: we could pass index configuration as a second argument, see docs for details */
            );
        }
    }

    public function handle(string $appVersion, string $projectionName, Message $event): void
    {
        $collection = $this->generateCollectionName($appVersion, $projectionName);

        switch ($event->messageName()) {
            case Event::USER_CHECKED_IN:
                $this->documentStore->addDoc(
                    $collection,
                    $event->get(Payload::NAME), //Use username as doc id
                    [Payload::BUILDING_ID => $event->get(Payload::BUILDING_ID)]
                );
                break;
            case Event::USER_CHECKED_OUT:
                $this->documentStore->deleteDoc($collection, $event->get(Payload::NAME));
                break;
            default:
                //Ignore unknown events
        }
    }

    public function deleteReadModel(string $appVersion, string $projectionName): void
    {
        $this->documentStore->dropCollection($this->generateCollectionName($appVersion, $projectionName));
    }

    private function generateCollectionName(string $appVersion, string $projectionName): string
    {
        //We can use the naming strategy of the aggregate projector for our custom projection, too
        return AggregateProjector::generateCollectionName($appVersion, $projectionName);
    }
}

```
Make the projector available as a service in `src/Service/ServiceFactory`:

```php
<?php

namespace App\Service;

use App\Infrastructure\Projector\UserBuildingList;
use ...

final class ServiceFactory
{
    use ServiceRegistry;

    /**
     * @var ArrayReader
     */
    private $config;

    /**
     * @var ContainerInterface
     */
    private $container;

    /* ... */

    //Finders
    public function buildingFinder(): BuildingFinder
    {
        return $this->makeSingleton(BuildingFinder::class, function () {
            return new BuildingFinder(
                AggregateProjector::aggregateCollectionName(
                    $this->eventMachine()->appVersion(),
                    Aggregate::BUILDING
                ),
                $this->documentStore()
            );
        });
    }

    //Projectors
    public function userBuildingListProjector(): UserBuildingList
    {
        return $this->makeSingleton(UserBuildingList::class, function () {
            return new UserBuildingList($this->documentStore());
        });
    }

    /* ... */
}
```

And describe the projector in `src/Api/Projection`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Infrastructure\Projector\UserBuildingList;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\Persistence\Stream;

class Projection implements EventMachineDescription
{
    const USER_BUILDING_LIST = 'user_building_list';

    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->watch(Stream::ofWriteModel())
            ->withAggregateProjection(Aggregate::BUILDING);

        $eventMachine->watch(Stream::ofWriteModel())
            ->with(self::USER_BUILDING_LIST, UserBuildingList::class)
            ->filterEvents([
                Event::USER_CHECKED_IN,
                Event::USER_CHECKED_OUT,
            ]);
    }
}

```

If you look at the Postgres DB you should see a new table called `em_ds_user_building_list_0_1_0` but the table is empty.
We can reset the long-running projection process used by Event Machine and therefor recreate all read models.
This will fill the new read model with data from the past. That's cool, isn't it?

Run the command `docker-compose run php php bin/reset.php` in the project directory and check the table again.

Here we go:

id | doc
---|---
John | {"buildingId": "9ee8d8a8-3bd3-4425-acee-f6f08b8633bb"}

## Look up

We can add a new query, finder and corresponding type definitions to complete the look up feature.

*src/Api/Type*
```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Model\Building;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type\ObjectType;

class Type implements EventMachineDescription
{
    const HEALTH_CHECK = 'HealthCheck';
    const USER_BUILDING = 'UserBuilding'; //<-- new type

    /* ... */

    private static function userBuilding(): ObjectType
    {
        return JsonSchema::object([
            'user' => Schema::username(),
            'building' => Schema::building()->asNullable(), //<-- type ref to building, can be null
        ]);
    }

    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
        //Register the HealthCheck type returned by @see \App\Api\Query::HEALTH_CHECK
        $eventMachine->registerType(self::HEALTH_CHECK, self::healthCheck());

        $eventMachine->registerType(Aggregate::BUILDING, Building\State::__schema());

        $eventMachine->registerType(self::USER_BUILDING, self::userBuilding()); //<-- type registration
    }
}

```
*src/Api/Schema*
```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type\ArrayType;
use Prooph\EventMachine\JsonSchema\Type\StringType;
use Prooph\EventMachine\JsonSchema\Type\TypeRef;
use Prooph\EventMachine\JsonSchema\Type\UuidType;

class Schema
{
    /* ... */

    public static function username(): StringType
    {
        return JsonSchema::string()->withMinLength(1);
    }

    public static function userBuilding(): TypeRef
    {
        return JsonSchema::typeRef(Type::USER_BUILDING);
    }

    /* ... */
}

```

*src/Infrastructure/Finder/UserBuildingFinder*
```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Finder;

use App\Api\Payload;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Persistence\DocumentStore;
use React\Promise\Deferred;

final class UserBuildingFinder
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var string
     */
    private $userBuildingCollection;

    /**
     * @var string
     */
    private $buildingCollection;

    public function __construct(DocumentStore $documentStore, string $userBuildingCol, string $buildingCol)
    {
        $this->documentStore = $documentStore;
        $this->userBuildingCollection = $userBuildingCol;
        $this->buildingCollection = $buildingCol;
    }

    public function __invoke(Message $query, Deferred $deferred): void
    {
        $userBuilding = $this->documentStore->getDoc(
            $this->userBuildingCollection,
            $query->get(Payload::NAME)
        );

        if(!$userBuilding) {
            $deferred->resolve([
                'user' => $query->get(Payload::NAME),
                'building' => null
            ]);
            return;
        }

        $building = $this->documentStore->getDoc(
            $this->buildingCollection,
            $userBuilding['buildingId']
        );

        if(!$building) {
            $deferred->resolve([
                'user' => $query->get(Payload::NAME),
                'building' => null
            ]);
            return;
        }

        $deferred->resolve([
            'user' => $query->get(Payload::NAME),
            'building' => $building
        ]);
        return;
    }
}

```

*src/Service/ServiceFactory*
```php
<?php

namespace App\Service;

use App\Infrastructure\Finder\UserBuildingFinder;
use ...

final class ServiceFactory
{
    use ServiceRegistry;

    /**
     * @var ArrayReader
     */
    private $config;

    /**
     * @var ContainerInterface
     */
    private $container;

    /* ... */

    //Finders
    public function userBuildingFidner(): UserBuildingFinder
    {
        return $this->makeSingleton(UserBuildingFinder::class, function () {
            return new UserBuildingFinder(
                $this->documentStore(),
                AggregateProjector::generateCollectionName(
                    $this->eventMachine()->appVersion(),
                    Projection::USER_BUILDING_LIST
                ),
                AggregateProjector::aggregateCollectionName(
                    $this->eventMachine()->appVersion(),
                    Aggregate::BUILDING
                )
            );
        });
    }
    /* ... */
}
```

*src/Api/Query*
```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Infrastructure\Finder\UserBuildingFinder;
use ...

class Query implements EventMachineDescription
{
    /**
     * Default Query, used to perform health checks using messagebox or GraphQL endpoint
     */
    /* ... */
    const USER_BUILDING = 'UserBuilding';

    public static function describe(EventMachine $eventMachine): void
    {
        /* ... */

        $eventMachine->registerQuery(
            self::USER_BUILDING,
            JsonSchema::object(['name' => Schema::username()])
        )
            ->resolveWith(UserBuildingFinder::class)
            ->setReturnType(Schema::userBuilding());
    }
}

```
*Swagger - UserBuilding query*
```json
{
  "payload": {
    "name": "John"
  }
}
```

*Response*
```json
{
  "user": "John",
  "building": {
    "name": "Acme Headquarters",
    "users": [
      "John"
    ],
    "buildingId": "9ee8d8a8-3bd3-4425-acee-f6f08b8633bb"
  }
}
```
An hour of work (with a bit more practice even less) and we are ready to ship the new feature! Rapid application development at its best!
RAD is ok, but please don't skip testing! In the second bonus part of the tutorial we'll learn that Event Machine makes it
easy to run integration tests. Don't miss it!
