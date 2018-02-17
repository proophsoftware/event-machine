Part VI - Check in User

The second use case of our Building Management system checks in users into buildings. Users are identified by their name.

## Command

Let's add a new command for the use case in `src/Api/Command`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;

class Command implements EventMachineDescription
{
    const ADD_BUILDING = 'AddBuilding';
    const CHECK_IN_USER = 'CheckInUser';

    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
        /* ... */

        $eventMachine->registerCommand(
            Command::CHECK_IN_USER,
            JsonSchema::object([
                Payload::BUILDING_ID => Schema::buildingId(),
                Payload::NAME => Schema::username(),
            ])
        );
    }
}

```
We can reuse `Payload::NAME` but assign a different schema so that we can change schema for a `building name` without
influencing the schema of `user name`:

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
}

```
## Event

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

    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->registerEvent(
            self::BUILDING_ADDED,
            JsonSchema::object(
                [
                    Payload::BUILDING_ID => Schema::buildingId(),
                    Payload::NAME => Schema::buildingName(),
                ]
            )
        );
        
        $eventMachine->registerEvent(
            self::USER_CHECKED_IN,
            JsonSchema::object([
                Payload::BUILDING_ID => Schema::buildingId(),
                Payload::NAME => Schema::username(),
            ])
        );
    }
}

```

## Aggregate

Did you notice that we are getting faster? Once, you're used to Event Machine's API you can develop at the
speed of light ;).

A user can only check into an existing building. `builidngId` is part of the command payload and should reference a
building in our system. For the command handling aggregate function this means that we also have state of the aggregate
and Event Machine will pass that state as the first argument to the command handling function as well as to the
event apply function:

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
        yield [Event::USER_CHECKED_IN, $checkInUser->payload()];
    }
    
    public static function whenUserCheckedIn(Building\State $state, Message $userCheckedIn): Building\State
    {
        return $state->withCheckedInUser($userCheckedIn->get(Payload::NAME));
    }
}

``` 

`Building::checkInUser()` is still a dumb function (we change that in a minute) but `Building::whenUserCheckedIn()` 
contains an interesting detail. `Building\State` is an immutable record. But we can add `with*` methods to it to
modify state. You may know these `with*` methods from the `PSR-7` standard. It is a common practice to prefix
state changing methods of immutable objects with `with`. Those methods should return a new instance with the modified
state rather than changing its own state. Here is the implementation of `Building\State::withCheckedInUser(string $username): Building\State`:

```php
<?php
declare(strict_types=1);

namespace App\Model\Building;

use App\Api\Schema;
use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\Data\ImmutableRecordLogic;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type;

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
     * @var array
     */
    private $users = [];

    public static function __schema(): Type
    {
        return self::generateSchemaFromPropTypeMap([
            'users' => JsonSchema::TYPE_STRING
        ]);
    }

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

    /**
     * @return array
     */
    public function users(): array
    {
        return array_keys($this->users);
    }

    public function withCheckedInUser(string $username): State
    {
        $copy = clone $this;
        $copy->users[$username] = null;
        return $copy;
    }
    
    public function isUserCheckedIn(string $username): bool 
    {
        return array_key_exists($username, $this->users);
    }
}

```

Technically we can make a copy of the record and modify that. The original record is not modified
and we return the copy to satisfy the immutable record contract.

Besides `withCheckedInUser` we've added a new property `users` and a getter for it. We also override the `__schema`
method of `ImmutableRecordLogic` to pass a type hint to `ImmutableRecordLogic::generateSchemaFromPropTypeMap()`.
Unfortunately, it is not possible in PHP to use return type hints like `string[]`. We can only type hint for `array`.
Hopefully this will change in a future version of PHP. For now we have to live with the workaround and give 
`ImmutableRecordLogic` a hint that array items of the `users` property are of type `string`.

*Note: ImmutableRecordLogic derives type information by inspecting return types of getter methods named like their
corresponding private properties.*

Internally, user names are used as array index. So the same user cannot appear twice in the list. With `Building\State::isUserCheckedIn(string $username): bool`
we can look up if the given user is currently in the building. `Building\State::users()` on the other hand returns a list
of user names like defined in the `__schema`. Internal state is used for fast look ups and external schema is used for the
read model. More on that in a minute.

## Command Processing

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
            ->apply([Building::class, 'whenUserCheckedIn']);
    }
}

```

Pretty much the same command processing description just with replaced command, event and function names according
to the new use case. An important difference is that we use `->withExisting` instead of `->withNew`. 
As already stated this tells Event Machine to look up an existing Building using the `buildingId` from the `CheckInUser` command.

The following GraphQL mutation should check in *John* into the *Acme Headquarters*.

```graphql
mutation{
  CheckInUser(
    buildingId:"122a63bf-7388-4cc0-b615-c5cc857a9adc"
    name:"John"
  )
}
```

Response:

```json
{
  "data": {
    "CheckInUser": true
  }
}
```

Looks good! And how does the response of the `Buildings` query look now? If you inspect the GraphQL schema of the query
and click on the `Building` return type you'll notice the new property `users: [String!]!`. We can tell GraphQL to
include the new property in the response:

```graphql
query{
  Buildings(name:"Acme") {
    buildingId
    name
    users
  }
}
```
Response

```json
{
  "data": {
    "Buildings": [
      {
        "buildingId": "122a63bf-7388-4cc0-b615-c5cc857a9adc",
        "name": "Acme Headquarters",
        "users": [
          "John"
        ]
      }
    ]
  }
}
```
Great! We get back the list of users checked in the building.

## Protect Invariants

One of the main tasks of an aggregate is to protect invariants. A user cannot check in twice. The `Building` aggregate
should enforce the business rule:

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
            throw new \DomainException(sprintf(
                "User %s is already in the building",
                $checkInUser->get(Payload::NAME)
            ));
        }
        
        yield [Event::USER_CHECKED_IN, $checkInUser->payload()];
    }

    public static function whenUserCheckedIn(Building\State $state, Message $userCheckedIn): Building\State
    {
        return $state->withCheckedInUser($userCheckedIn->get(Payload::NAME));
    }
}

```

The command handling function can make use of `$state` passed to it as this will always be the current state of the aggregate.
If the given user is already checked in we throw an exception to stop command processing.

Let's try it:

```graphql
mutation{
  CheckInUser(
    buildingId:"122a63bf-7388-4cc0-b615-c5cc857a9adc", 
    name:"John"
  )
}
```

Response:

```json
{
  "errors": [
    {
      "debugMessage": "User John is already in the building",
      "message": "Internal server error",
      "category": "internal",
      "locations": [
        {
          "line": 28,
          "column": 3
        }
      ],
      "path": [
        "CheckInUser"
      ]
    }
  ],
  "data": []
}
```

Throwing an exception is the simplest way to protect invariants. However, with event sourcing we have a different
(and in most cases) better option. This will be covered in the next part of the tutorial.
