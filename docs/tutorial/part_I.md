# Part I - Add A Building

We're going to add the first action to our buildings application. In a CQRS system, such as
Event Machine, operations and processes are triggered by messages. Those messages can have three different types and
define the API of the application. In the first part of the tutorial we learn the first message type: `command`.

## API

The Event Machine skeleton includes an API folder (src/Api) that contains a predefined set of `EventMachineDescription` classes.
We will look at these descriptions step by step and start with `src/Api/Command.php`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;

class Command implements EventMachineDescription
{
    /**
     * Define command names using constants
     *
     * @example
     *
     * const REGISTER_USER = 'RegisterUser';
     */


    /**
     * @param EventMachine $eventMachine
     */
    public static function describe(EventMachine $eventMachine): void
    {
        //Describe commands of the service and corresponding payload schema (used for input validation)
    }
}

```

The `Command` description is used to group all commands of our application into one file and add semantic meaning to our
code. Replace the comment with a real constant `const ADD_BUILDING = 'AddBuilding';` and register the command in the
`describe` method.

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
                    'name' => JsonSchema::string(['minLength' => 2])
                ]
            )
        );
    }
}

```
Event Machine uses [JSON Schema](http://json-schema.org/) to describe messages.
The advantage of JSON schema is that we can configure validation rules for our messages. Whenever Event Machine receives a message
(command, event or query) it uses the defined JSON Schema for that message to validate the input. We configure it once
and Event Machine takes care of the rest.

*Note: The skeleton defines the namespace `App` in composer.json and maps it to the `src` directory. You can change that for your own project, but
you need to change the namespaces of the classes/interfaces shipped with the skeleton which are located in the `src` directory (just a few helpers)*

## Descriptions

Event Machine Descriptions are very important. They are called at "**compile time**" and used to configure Event Machine.
Later in the tutorial we learn more about using Event Machine in production. In production mode the descriptions are only
called once and cached to speed up bootstrapping.
The GraphQL schema is also compiled at this stage. In development mode this happens on every request.

## GraphQL Integration

Switch to the GraphQL client and reload it (press Set endpoint button).
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
      "debugMessage": "CommandBus was not able to identify a CommandHandler for command AddBuilding",
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
commands can be routed directly to `Aggregates`.
In **part II** of the the tutorial you'll learn more about pure aggregates.

*Sum up: Event Machine Descriptions allow you to easily describe the API of your application using messages. The messages get
a unique name and their payload is described with JSON Schema which allow us to add validation rules. The messages and their
schema are translated to a GraphQL Schema and we can use GraphQL queries and mutations to interact with the backend
service.*










