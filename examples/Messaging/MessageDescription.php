<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\Messaging;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type\ArrayType;
use Prooph\EventMachine\JsonSchema\Type\EmailType;
use Prooph\EventMachine\JsonSchema\Type\StringType;
use Prooph\EventMachine\JsonSchema\Type\UuidType;
use ProophExample\Aggregate\UserDescription;
use ProophExample\Resolver\GetUserResolver;
use ProophExample\Resolver\GetUsersResolver;

/**
 * You're free to organize EventMachineDescriptions in the way that best fits your personal preferences
 *
 * We decided to describe all messages of the bounded context in a centralized MessageDescription.
 * Another idea would be to register messages within an aggregate description.
 *
 * You only need to follow one rule:
 * Messages need be registered BEFORE they are referenced by handling or listing descriptions
 *
 * Class MessageDescription
 * @package ProophExample\Messaging
 */
final class MessageDescription implements EventMachineDescription
{
    public static function describe(EventMachine $eventMachine): void
    {
        /* Schema Definitions */
        $userId = new UuidType();

        $username = (new StringType())->withMinLength(1);

        $userDataSchema = JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
            UserDescription::EMAIL => new EmailType(),
        ], [
            //If it is set to true user registration handler will record a UserRegistrationFailed event
            //when using CachableUserFunction
            'shouldFail' => JsonSchema::boolean(),
        ]);

        /* Message Registration */
        $eventMachine->registerCommand(Command::REGISTER_USER, $userDataSchema);
        $eventMachine->registerCommand(Command::CHANGE_USERNAME, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
        ]));
        $eventMachine->registerCommand(Command::DO_NOTHING, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
        ]));
        $eventMachine->registerCommand(Command::CALL_EXTERNAL_SERVICE, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
        ]));

        $eventMachine->registerEvent(Event::USER_WAS_REGISTERED, $userDataSchema);
        $eventMachine->registerEvent(Event::USERNAME_WAS_CHANGED, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            'oldName' => $username,
            'newName' => $username,
        ]));

        $eventMachine->registerEvent(Event::USER_REGISTRATION_FAILED, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
        ]));

        $eventMachine->registerEvent(Event::EXTERNAL_SERVICE_WAS_CALLED, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            'dataFromExternalService' => new ArrayType(new StringType()),
        ]));

        //Register user state as a Type so that we can reference it as query return type
        $eventMachine->registerType('User', $userDataSchema);
        $eventMachine->registerQuery(Query::GET_USER, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
        ]))
        ->resolveWith(GetUserResolver::class)
        ->setReturnType(JsonSchema::typeRef('User'));

        $eventMachine->registerQuery(Query::GET_USERS)
            ->resolveWith(GetUsersResolver::class)
            ->setReturnType(JsonSchema::array(JsonSchema::typeRef('User')));

        $filterInput = JsonSchema::object([
            'username' => JsonSchema::nullOr(JsonSchema::string()),
            'email' => JsonSchema::nullOr(JsonSchema::email()),
        ]);
        $eventMachine->registerQuery(Query::GET_FILTERED_USERS, JsonSchema::object([], [
            'filter' => JsonSchema::nullOr(JsonSchema::typeRef('UserFilterInput')),
        ]))
            ->resolveWith(GetUsersResolver::class)
            ->setReturnType(JsonSchema::array(JsonSchema::typeRef('User')));
    }
}
