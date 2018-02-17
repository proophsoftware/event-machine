# Part IV - Projections and Queries

In part III of the tutorial we successfully implemented the first write model use case: *Add a new building*.
Connect to the Postgres database and check the event stream table `_4228e4a00331b5d5e751db0481828e22a2c3c8ef`.
The table should contain the first domain event yielded by the `Building` aggregate and recorded by event machine.

no | event_id | event_name | payload | metadata | created_at
---|-----------|------------|--------|--------|---------
1 | bce42506-...| BuildingAdded | {"buildingId":"122a63bf-...","name":"Acme Headquarters"} | {"_aggregate_id": "122a63bf-...", "_causation_id": "e482f5b8-...", "_aggregate_type": "Building", "_causation_name": "AddBuilding", "_aggregate_version": 1} | 2018-02-14 22:09:32.039848

*If you're wondering why the event stream table has a sha1 hashed name this is because by default prooph/event-store uses that
naming strategy to avoid database vendor specific character constraints. You can however configure a different
naming strategy if you don't like it.*

The write model only needs an event stream to store information but the read side has a hard time querying it.
As long as we only have a few events in the stream queries are simple and fast. But over time this table will
grow and contain many different events. To stay flexible we need to
separate the write side from the read side. And this is done using so called **projections**.

## Registering Projections

Projections in Event Machine make use of the projection feature shipped with *prooph/event-store*.
An important difference is that by default Event Machine uses **a single long-running PHP process** to manage
those projections. This way processing order of events is always the same as (FIFO).
A disadvantage is that projections are slower because of the sequential processing.

But don't worry: If projections become a bottleneck you can simply switch to plain *prooph/event-store*
projections and run them in parallel. The recommendation is to switch to that approach only if it is really needed.
Deploying and coordinating multiple projection processes need a good (project specific) strategy and tools. 

Ok enough theory. Let's get back to the beauty and simplicity of Event Machine. You can use a shortcut if aggregate 
state should be available as a read model. You only need one of the available `EventMachine\Persistence\DocumentStore`
implementations. By default the skeleton uses *proophsoftware/postgres-document-store* but you can also use 
*proophsoftware/mongo-document-store* or implement your own. See Event Machine docs for details.

We only need to register an aggregate projection in `src/Api/Projection`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\Persistence\Stream;

class Projection implements EventMachineDescription
{
    /**
     * You can register aggregate and custom projections in event machine
     *
     * For custom projection you should define a unique projection name using a constant
     *
     * const USER_FRIENDS = 'UserFriends';
     */

    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->watch(Stream::ofWriteModel())
            ->withAggregateProjection(Aggregate::BUILDING);
    }
}

``` 

That's it. If you look into the Postgres DB you should see a new table called `em_ds_building_projection_0_1_0`.
And the table should contain one row with two columns `id` and `doc` with id being the buildingId and doc being the
JSON representation of the `Building\State`.

*Note: If you cannot see the table please check the troubleshooting section of event-machine-skeleton README.*

You can learn more about projections in the docs. For now it is enough to know how to register them. Let's complete the picture
and query the projection table using GraphQL.

## Query, Resolver and Return Type

GraphQL has a type system that makes it possible to auto generate API docs and provide auto completion when writing GraphQL
mutations and queries. Event Machine on the other hand uses JSON Schema to describe message types and define validation rules.
It is possible to generate a GraphQL schema from a JSON Schema as long as only a subset of JSON Schema is used.
You get the best of both worlds.

Event Machine offers an API that eases integration of JSON Schema and GraphQL. For queries this means that we can
register **return types** in Event Machine and those return types will appear in our GraphQL schema definition.

Registering types is done in `src/Api/Type`:

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

    private static function healthCheck(): ObjectType
    {
        return JsonSchema::object([
            'system' => JsonSchema::boolean()
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
    }
}

```

As you can see the `HealthCheck` type used by the `HealthCheck` query is already registered here. We simply add
`Building\State` as the second type and use the aggregate type as name for the building type.

*Note: Types are described using JSON Schema. Building\State implements ImmutableRecord and therefor provides the method
ImmutableRecord::__schema (provided by ImmutableRecordLogic trait) which returns a JSON Schema object.*

*Note: Using aggregate state as return type for queries couples the write model with the read model.
However, you can replace the return type definition at any time. So we can use the short cut
in an early stage and switch to a decoupled version later.*

Next step is to register the query in `src/Api/Query`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Infrastructure\System\HealthCheckResolver;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;

class Query implements EventMachineDescription
{
    /**
     * Default Query, used to perform health checks using messagebox or GraphQL endpoint
     */
    const HEALTH_CHECK = 'HealthCheck';
    
    const BUILDING = 'Building';

    public static function describe(EventMachine $eventMachine): void
    {
        //Default query: can be used to check if service is up and running
        $eventMachine->registerQuery(self::HEALTH_CHECK) //<-- Payload schema is optional for queries
            ->resolveWith(HealthCheckResolver::class) //<-- Service id (usually FQCN) to get resolver from DI container
            ->returnType(Schema::healthCheck()); //<-- Type returned by resolver, this is converted to a GraphQL type

        $eventMachine->registerQuery(self::BUILDING, JsonSchema::object([
            'buildingId' => JsonSchema::uuid(),
        ]))
            ->resolveWith(/* ??? */)
            ->returnType(JsonSchema::typeRef(Aggregate::BUILDING));
    }
}

```

Queries are named like the "things" they return. This results in a clean and easy to use GraphQL schema.

Please note that the return type is a reference: `JsonSchema::typeRef()`.
In GraphQL this resolves to the type defined in `src/Api/Type`.

Last but not least the query needs to be handled by a so called finder (prooph term). If you've worked with GraphQL
before a finder is similar to a resolver. Query resolving works like this:

When the query is send to the GraphQL endpoint it is parsed by the integrated GraphQL server and translated into a
query message that is passed on to prooph's query bus. The query message is validated against the schema
defined during query registration `$eventMachine->registerQuery(self::BUILDING, JsonSchema::object(...))`.

Our first query has a required argument `buildingId` which should be a valid `Uuid`. The GraphQL type is a `string`.
An invalid uuid will pass the GraphQL server but fail when the query is parsed into a Event Machine message.
It's a two step validation process handled internally by Event Machine.

Long story, short. We need a finder like described in the [prooph docs](https://github.com/prooph/service-bus/blob/master/docs/message_bus.md#invoke-handler):

> QueryBus: much the same as the command bus but the message handler is invoked with the query message and a React\Promise\Deferred that needs to be resolved by the message handler aka finder. 

Create a new class called `BuildingFinder` in a new directory `Finder` in `src/Infrastructure`.

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Finder;

use Prooph\EventMachine\Messaging\Message;
use React\Promise\Deferred;

final class BuildingFinder
{
    public function __invoke(Message $buildingQuery, Deferred $deferred): void
    {
        //@TODO: resolve $deferred
    }
}

```

This is an **invokable finder** like it is described in the prooph docs. It receives the query message as first argument
and a `React\Promise\Deferred` as second argument. Instead of returning the query result a finder has to resolve
the deferred object. The reason for this is that prooph's query bus can be used in an async non-blocking I/O runtime
as well as a normal blocking PHP runtime. The integrated GraphQL server also supports both runtimes so it is a good
idea to always work with those promise and deferred objects provided by the `ReactPHP` library (unfortunately, we have no
PSR for promises yet). Event Machine takes care of resolving promises returned by prooph's query bus.  

The finder needs to query the read model. While looking at projections we learned about
Event Machine's `DocumentStore` API. The finder can use it to access documents organized in collections. Let's see
how that works.

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Finder;

use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Persistence\DocumentStore;
use React\Promise\Deferred;

final class BuildingFinder
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var string
     */
    private $collectionName;

    public function __construct(string $collectionName, DocumentStore $documentStore)
    {
        $this->collectionName = $collectionName;
        $this->documentStore = $documentStore;
    }

    public function __invoke(Message $buildingQuery, Deferred $deferred): void
    {
        $buildingId = $buildingQuery->get('buildingId');

        $buildingDoc = $this->documentStore->getDoc($this->collectionName, $buildingId);

        if(!$buildingDoc) {
            $deferred->reject(new \RuntimeException('Building not found', 404));
            return;
        }

        $deferred->resolve($buildingDoc);
    }
}

```

The implementation is self explanatory. Just a few notes: 

Every Event Machine message has a `get` and a `getOrDefault`
method which are both short cuts to access keys of the message payload. The difference between the two is obvious.
If payload key is NOT set and you use `get` the message will throw an exception. If payload key is NOT set and you use
`getOrDefault` you get back the default passed as a second argument.

The second note is about the *collection name*. It is injected at runtime rather than defined as a hardcoded string or
constant. Do you remember the read model table name `em_ds_building_projection_0_1_0`?
First of all this is again a default naming strategy and can be changed. However, the interesting part here is the version
number at the end of the name. This is the **application version** which you can pass to `EventMachine::boostrap()` (see docs for details).
When deploying a new application version it is possible to rebuild all projection tables using the new version while
the old projection tables remain active until load balancers are switched (Blue Green Deployment).

As a last step we need to configure Event Machine's DI container to inject the dependencies into our new finder.

## PSR-11 Container

EventMachine can use any PSR-11 compatible container. By default it uses a very simple implementation included
in the Event Machine package. The DI container is inspired by `bitExpert/disco` but removes the need for annotations.
Dependencies are managed in a single `ServiceFactory` class which is located in `src/Service`.

Just add the following method to the `ServiceFactory`:

```php
<?php

namespace App\Service;

//New use statements
use App\Api\Aggregate;
use App\Infrastructure\Finder\BuildingFinder;
use Prooph\EventMachine\Projecting\AggregateProjector;
//Other use statements
use ...

final class ServiceFactory
{
    /* ... */

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    //Finders
    public function buildingFinder(): BuildingFinder //<-- Return type is used as service id
    {
        //Service is treated as a singleton, DI returns same instance on subsequent gets
        return $this->makeSingleton(BuildingFinder::class /*<-- again service id */, function () {
            return new BuildingFinder(
                //We can use the AggregateProjector to generate correct collection name
                AggregateProjector::aggregateCollectionName(
                    $this->eventMachine()->appVersion(), //<-- Insight a closure we still have access to other methods
                    Aggregate::BUILDING                  //    of the ServiceFactory like the getter for Event Machine itself
                ),
                $this->documentStore()                   //    or the document store
            );
        });
    }
    /* ... */
}

```
And use `BuildingFinder::class` as the finder service id when registering the query in `src/Api/Query`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Infrastructure\Finder\BuildingFinder; //<-- New use statement
use App\Infrastructure\System\HealthCheckResolver;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;

class Query implements EventMachineDescription
{
    /**
     * Default Query, used to perform health checks using messagebox or GraphQL endpoint
     */
    const HEALTH_CHECK = 'HealthCheck';
    
    const BUILDING = 'Building';

    public static function describe(EventMachine $eventMachine): void
    {
        /* ... */

        $eventMachine->registerQuery(self::BUILDING, JsonSchema::object([
            'buildingId' => JsonSchema::uuid(),
        ]))
            ->resolveWith(BuildingFinder::class) //<-- Finder service id
            ->returnType(JsonSchema::typeRef(Aggregate::BUILDING));
    }
}

```
Ok! We should be able to query buildings by buildingId now. Switch to the GraphQL client and reload the schema (press button "Set endpoint").
The Documentation Explorer should show a new Query:  `Building(buildingId: String!): Building!`.
If we send that query with the `buildingId` used in `AddBuilding`:

```graphql
query{
  Building(buildingId:"122a63bf-7388-4cc0-b615-c5cc857a9adc") {
    buildingId
    name
  }
}
```
We get back:

```json
{
  "data": {
    "Building": {
      "buildingId": "122a63bf-7388-4cc0-b615-c5cc857a9adc",
      "name": "Acme Headquarters"
    }
  }
}
```

Awesome, isn't it?

## Optional Query Arguments

Finders can also handle multiple queries. This is useful when multiple queries can be resolved by accessing the same
read model collection. A second query for the `BuildingFinder` would be a query that lists all buildings or a subset filtered
by name.

Add the query to `src/Api/Query`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Infrastructure\Finder\BuildingFinder;
use App\Infrastructure\System\HealthCheckResolver;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;

class Query implements EventMachineDescription
{
    const HEALTH_CHECK = 'HealthCheck';
    const BUILDING = 'Building';
    const BUILDINGS = 'Buildings'; //<-- New query, note the plural

    public static function describe(EventMachine $eventMachine): void
    {
        /* ... */

        $eventMachine->registerQuery(self::BUILDING, JsonSchema::object([
            'buildingId' => JsonSchema::uuid(),
        ]))
            ->resolveWith(BuildingFinder::class)
            ->returnType(JsonSchema::typeRef(Aggregate::BUILDING));

        //New query
        $eventMachine->registerQuery(
            self::BUILDINGS,
            JsonSchema::object(
                [], //No required arguments for that query
                //Optional argument name, is a nullable string
                ['name' => JsonSchema::nullOr(JsonSchema::string()->withMinLength(1))]
            )
        )
            //Resolve query with same finder ...
            ->resolveWith(BuildingFinder::class)
            //... but return an array of Building type
            ->returnType(JsonSchema::array(
                JsonSchema::typeRef(Aggregate::BUILDING)
            ));
    }
}

```

The refactored `BuildingFinder` looks like this:

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Finder;

use App\Api\Query;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Persistence\DocumentStore;
use React\Promise\Deferred;

final class BuildingFinder
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var string
     */
    private $collectionName;

    public function __construct(string $collectionName, DocumentStore $documentStore)
    {
        $this->collectionName = $collectionName;
        $this->documentStore = $documentStore;
    }

    public function __invoke(Message $buildingQuery, Deferred $deferred): void
    {
        switch ($buildingQuery->messageName()) {
            case Query::BUILDING:
                $this->resolveBuilding($deferred, $buildingQuery->get('buildingId'));
                break;
            case Query::BUILDINGS:
                $this->resolveBuildings($deferred, $buildingQuery->getOrDefault('name', null));
                break;
        }
    }

    private function resolveBuilding(Deferred $deferred, string $buildingId): void
    {
        $buildingDoc = $this->documentStore->getDoc($this->collectionName, $buildingId);

        if(!$buildingDoc) {
            $deferred->reject(new \RuntimeException('Building not found', 404));
            return;
        }

        $deferred->resolve($buildingDoc);
    }

    private function resolveBuildings(Deferred $deferred, string $nameFilter = null): array
    {
        $filter = $nameFilter?
            new DocumentStore\Filter\LikeFilter('name', "%$nameFilter%")
            : new DocumentStore\Filter\AnyFilter();

        $cursor = $this->documentStore->filterDocs($this->collectionName, $filter);

        $deferred->resolve(iterator_to_array($cursor));
    }
}

```

`BuildingFinder` can resolve both queries by mapping the query name to an internal resolve* method.
For the new `Buildings` query the finder makes use of `DocumentStore\Filter`s. The `LikeFilter` works the same way as
a SQL like expression using `%` as a placeholder. `AnyFilter` matches any documents in the collection.
There are many more filters available. Read more about filters in the docs.

Of course you can test the new query using GraphQL. The GraphQL query schema looks like this: `Buildings(name: String): [Building!]!`
and this is an example query with a name filter:

```graphql
query{
  Buildings(name:"Acme") {
    buildingId
    name
  }
}
``` 
You can add some more buildings and play with the queries. Try to exchange the `LikeFilter` with a `EqFilter` for example.
Or see what happens if you pass an empty string as name filter.

In part VI we get back to the write model and learn how to work with process managers. But before we continue,
we should clean up our code a bit. Part V tells you what we can improve.







