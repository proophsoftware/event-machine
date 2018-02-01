# Part I - Add A Building

We're going to add the first action to our buildings application. Event Machine uses CQRS and Event Sourcing.
In a CQRS system operations and processes are triggered by messages. Those messages can have three different types and
define the API of the application. In this part of the tutorial we learn the first message type: `command`.

## API

The Event Machine skeleton includes an API folder (src/Api) that contains a few interfaces and a `Schema` class.
We will look at these interfaces step by step and start with `src/Api/Command.php`:

```php
<?php
declare(strict_types=1);

namespace App\Api;

interface Command
{
    /**
     * Define command names using constants
     *
     * Note: If you use the GraphQL integration then make sure that your command names can be used as type names
     * in GraphQL. Dots for example do not work: MyContext.RegisterUser
     * Either use MyContext_RegisterUser or just MyContextRegisterUser. Event machine is best suited for single context
     * services anyway, so in most cases you don't need to set a context in front of your commands because the context
     * is defined by the service boundaries itself.
     *
     * @example
     *
     * const REGISTER_USER = 'RegisterUser';
     */
}
```

The `Command` interface is used to group all command names of our application in one file. It adds semantic meaning to our
code. Replace the comment with a real constant `const ADD_BUILDING = 'AddBuilding'`.

```php
<?php

declare(strict_types=1);

namespace App\Api;

interface Command
{
    const ADD_BUILDING = 'AddBuilding';
}
```
Next we need to define a schema for our new command. Event Machine uses [JSON Schema](http://json-schema.org/) to describe messages.
The advantage of JSON schema is that we can configure validation rules for our messages. Whenever Event Machine receives a message
(command, event or query) it uses the defined JSON Schema for that message to validate the input. We configure it once
and Event Machine takes care of the rest.

## Event Machine Descriptions

Switch to `src/Infrastructure` and create a new folder `Building`. Then add a new file called `BuildingDescription.php` that contains
a `BuildingDescription` class.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Building;

class BuildingDescription
{

}

```

*Note: The skeleton defines the namespace `App` in composer.json and maps it to the `src` directory. You can change that for your own project, but
you need to change the namespaces of the classes/interfaces shipped with the skeleton which are located in the `src` directory (just a few helpers)*

Event Machine defines the interface `Prooph\EventMachine\EventMachineDescription` that we need to implement now.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Building;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;

final class BuildingDescription implements EventMachineDescription
{

    public static function describe(EventMachine $eventMachine): void
    {
        // TODO: Implement describe() method.
    }
}

```

Event Machine Descriptions are very important. They are called at "**compile time**" and used to configure Event Machine.
Later in the tutorial we learn more about using Event Machine in production. In production mode the descriptions are only
called once and cached to speed up bootstrapping.
The GraphQL schema is also compiled at this stage. In development mode this happens on every request. Depending on the amount
of messages used in the application this can slow down requests in development mode but PHP 7 is extremely fast
and requests handled by Event Machine are fast, too. So even with > 500 message types in a single application/service this will not
block you.

### Register Command

You need to register your commands in Event Machine using `EventMachine::registerCommand(<name>, <schema>)`. 

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Building;

use App\Api\Command; //<-- Don't forget the use statements when
use App\Api\Schema;  //copy and pasting example code
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;

final class BuildingDescription implements EventMachineDescription
{

    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->registerCommand(
            Command::ADD_BUILDING,
            JsonSchema::object(
                [
                    'buildingId' => Schema::uuid(),
                    'name' => JsonSchema::string(['minLength' => 2])
                ]
            )
        );
    }
}

``` 
I'm going to explain command registration in a minute, but first let's see Event Machine in action.
Therefor we need to point Event Machine to our description. This can be done in the configuration located in
`config/autoload/global.php`. This file defines an array configuration and at the bottom you can find the key `event_machine`.
Add a reference to the newly created `BuildingDescription`:

```php
<?php
declare(strict_types = 1);

namespace App\Config;

use App\Infrastructure\Building\BuildingDescription; //<-- Don't forget the import
use App\Infrastructure\System\HealthCheckDescription;

return [
    'environment' => getenv('PROOPH_ENV')?: 'prod',
    'pdo' => [
        'dsn' => getenv('PDO_DSN'),
        'user' => getenv('PDO_USER'),
        'pwd' => getenv('PDO_PWD'),
    ],
    'rabbit' => [
        /*...*/
    ],
    'event_machine' => [
            'descriptions' => [
                HealthCheckDescription::class,
                BuildingDescription::class,
            ]
        ]
];
```

Once done switch to the GraphQL client and reload it (reload the schema).
The GraphQL client should show a new **mutation** called `AddBuilding` in the documentation explorer (in ChromeiQL it is on the right side).
When you start typing in the query window the GraphQL client will suggest possibilities. Just try it by typing `mutation { Add`.
Select `AddBuilding`, type `(` followed by `buildi`. The client should suggest `buildingId` as input argument.

Finally your mutation should look like this:

```graphql
mutation {
  AddBuilding(
    buildingId:"122a63bf-7388-4cc0-b615-c5cc857a9adc",
    name:"Acme Headquarters"
  )
}
``` 
Just hit the send button now. The mutation request will result in an error like this:

```json
{
  "errors": [
    {
      "debugMessage": "CommandBus was not able to identify a CommandHandler for ...",
      "message": "Internal server error",
      "category": "internal",
      "locations": [
        {
          "line": 28,
          "column": 3
        }
      ],
      "path": [
        "AddBuilding"
      ]
    }
  ],
  "data": []
}
```

Our command (aka GraphQL mutation) cannot be handled because a command handler is missing. In Event Machine 
commands are routed directly to `Aggregates`. 

*Note: If you've worked with a CQRS framework before this is maybe confusing
because normally a command is handled by a command handler (comparable to an application service that handles a domain action)
and the command handler would load a business entity or "DDD" aggregate from a repository.*

In Event Machine we take a short cut and skip the command handler.
This is possible because `Aggregates` in Event Machine are **stateless** and **pure**. This means that
they don't have internal **state** and also **no dependencies**. 

*Simply put: they are just functions*

Event Machine can take over the boilerplate and we as developers can **focus on the business logic**. I'll explain
more details later. First we want to see a **pure aggregate function** in action. 

Therefor we define the `Building` aggregate in our API overview (src/Api/Aggregate):

```php
<?php

declare(strict_types=1);

namespace App\Api;

interface Aggregate
{
    /**
     * Define aggregate names using constants
     *
     * @example
     *
     * const USER = 'User';
     */
    const BUILDING = 'Building';
}
```

and switch back to our `BuildingDescription` to add the following code:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Building;

use App\Api\Aggregate; //<-- Reminder: Don't forget the imports
use App\Api\Command;
use App\Api\Schema;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\Messaging\Message;

class BuildingDescription implements EventMachineDescription
{
    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->registerCommand(
            Command::ADD_BUILDING,
            JsonSchema::object(
                [
                    'buildingId' => Schema::uuid(),
                    'name' => JsonSchema::string(['minLength' => 2])
                ]
            )
        );

        //Describe how the command is processed by a NEW aggregate
        $eventMachine->process(Command::ADD_BUILDING)
            ->withNew(Aggregate::BUILDING)
            //When we describe a NEW aggregate we also need to define
            //which message property IS THE AGGREGATE IDENTIFIER
            //!!! Every command that is processed by the Building aggregate MUST contain a buildingId !!!
            ->identifiedBy('buildingId')
            //And next is the PURE AGGREGATE FUNCTION
            //Note: ONLY if we process a command WITH A NEW aggregate function,
            //we receive the command (message) as the only input argument
            //Follow up aggregate functions of the same aggregate will also receive a second input arg
            //more about that in the next tutorial part
            ->handle(function (Message $addBuilding) {
                //For now we break here and echo JSON
                //
                //!!!DO NOT DO THAT IN YOUR PRODUCTION CODE!!!
                //
                //we only do that because we want to take small steps
                //and therefor need to break out of Event Machine here
                //to see a response in the GraphQL client.
                //Go test it by pressing the send button in the GraphQL client!
                echo json_encode([
                    'name' => $addBuilding->get('name')
                ]);die();
            });
    }
}
```

**Exercise:** Add a `city` property to the `AddBuilding` command description. Then try to send the command from the GraphQL
client without adding a city argument. GraphQL will complain about the missing argument. Remove the `city` property from
command description and then try to send an empty building name. This time the JSON schema
validation will fail and your request will be blocked. Last but not least try to read a not defined property like
`location` from the message: `$addBuilding->get('location')`. In all cases you get detailed debug messages and that
is one of the strengthen of Event Machine. If you make a mistake Event Machine or the integrated third party
packages will complain about the error and tell you the exact problem. That's a design goal of Event Machine:

**Design Goal: Provide accurate and detailed debug messages to guide developers in their daily work and save unnecessary hours of searching a typo and similar mistakes.**

### Expressive Code

You might have noticed that the Event Machine API is very verbose `$eventMachine->proccessCommand()->withNewAggregate()->identifiedBy() ...`.
This reduces cognitive load when describing the behaviour of the application and gives new developers a fast and expressive
overview of what is going on in the code. Remember that those `EventMachineDescription`s are only called once in production mode 
(until you clear the cache during next deployment). Hence, all the description calls
are not expensive but instead become the best friend of the developer.
**Once you're used to them and the structure, you get REALLY REALLY fast in adding new behaviour to your backend.** 

To further improve readability and structure we can refactor the code from above a bit. So let's do that before moving
on to the next tutorial part.

### Improve Structure

First let's remove the strings!!!. Don't use repeatable strings in your code but use constants instead. This rule of thumb
has many advantages:

- Reduce the chance for typos
- Easy refactoring
- IDE auto completion and code navigation

Just move `buildingId` and `name` into one of the API interfaces. You guess what the right interface is?
Yes, that's right! It is `src/Api/Payload.php`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

interface Payload
{
    const BUILDING_ID = 'buildingId';
    const NAME = 'name';

    //Predefined keys for query payloads, see App\Api\Schema::queryPagination() for further information
    const SKIP = 'skip';
    const LIMIT = 'limit';
}

```

And `BuildingDescription` looks like this now (explanation comments are removed):

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Building;

use App\Api\Aggregate;
use App\Api\Command;
use App\Api\Payload; //<-- New import!
use App\Api\Schema;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\Messaging\Message;

class BuildingDescription implements EventMachineDescription
{
    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->registerCommand(
            Command::ADD_BUILDING,
            JsonSchema::object(
                [
                    Payload::BUILDING_ID => Schema::uuid(),
                    Payload::NAME => JsonSchema::string(['minLength' => 2])
                ]
            )
        );

        $eventMachine->process(Command::ADD_BUILDING)
            ->withNew(Aggregate::BUILDING)
            ->identifiedBy(Payload::BUILDING_ID)
            ->handle(function (Message $addBuilding) {
                echo json_encode([
                    Payload::NAME => $addBuilding->get(Payload::NAME)
                ]);die();
            });
    }
}

```
You see how readable this code is? Hope you enjoy it as much as I do ;) But we're not done, yet.
What happens if we want to add the next command `ChangeBuildingName`? We would need to copy the JSON schema definition
for `Payload::BUILIDNG_ID` and `Payload::NAME` even though, validation rules are the same. DRY (don't repeat yourself)
is not always bad but in this case it is! To avoid DRY Event Machine skeleton ships with a `Schema` class in `src/Api`.
Let's move the schema definition to that class:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventMachine\JsonSchema\JsonSchema;
use Ramsey\Uuid\Uuid;

class Schema
{
    public static function buildingId(): array
    {
        return self::uuid();
    }

    public static function buildingName(): array
    {
        JsonSchema::string(['minLength' => 2]);
    }


    /**
     * Common schema definitions that are useful in nearly any application.
     * Add more or remove unneeded depending on project needs.
     */
    const TYPE_HEALTH_CHECK = 'HealthCheck';
        
    /* ... */
}

```

As you can see the class contains a few common schema definitions. It's your turn to add the project specific ones.

*Note: Event though we use Payload::NAME for the building name property, we use Schema::buildingName() as method name.
Other messages may also contain a name property for other things and we can still use Payload::NAME to reference them, but not
all names will share the same validation rules like we define for building names. It is up to you if you prefer explicit 
message properties or keep the message API lean. We prefer the latter but that's really a matter of taste.*

And again the updated `BuildingDescription`.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Building;

use App\Api\Aggregate;
use App\Api\Command;
use App\Api\Payload;
use App\Api\Schema;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\Messaging\Message;

class BuildingDescription implements EventMachineDescription
{
    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->registerCommand(
            Command::ADD_BUILDING,
            JsonSchema::object(
                [
                    Payload::BUILDING_ID => Schema::buildingId(),
                    Payload::NAME =>Schema::buildingName()
                ]
            )
        );

        $eventMachine->process(Command::ADD_BUILDING)
            ->withNew(Aggregate::BUILDING)
            ->identifiedBy(Payload::BUILDING_ID)
            ->handle(function (Message $addBuilding) {
                echo json_encode([
                    Payload::NAME => $addBuilding->get(Payload::NAME)
                ]);die();
            });
    }
}

```

We're very close to a perfect Event Machine Description, but we have one remaining problem:

**The Description is not cachable!**

Our pure aggregate function causes the problem. Every description step like `$eventMachine->registerCommand()` is written
to a PHP array internally. Hence, the function used in `->handle()` is also written to that array.
As long as we don't want to write the array into a real cache but only keep it in memory everything is fine, but
closures (the function is a closure) cannot be cached.

But we can turn that disadvantage into an advantage and introduce a real domain model. At the moment our business logic
(echo JSON and die() ;) ) is located insight an Event Machine Description somewhere in the infrastructure layer.
That's not right. 

**A domain model should be separated from infrastructure and should have as less dependencies as possible,
ideally no dependencies at all.**

With Event Machine we can achieve a nearly independent domain model. The only dependency our domain model will have are
the messages which are passed around by Event Machine. 

*Note: You can get rid of the message dependency as well with a bit of own work,
but that's outside of the scope of our starter tutorial.*










