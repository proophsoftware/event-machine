<?php
declare(strict_types = 1);

namespace ProophExample\Messaging;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use ProophExample\Aggregate\UserDescription;

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
        $userId = [
            'type' => 'string',
            'minLength' => 36
        ];

        $username = [
            'type' => 'string',
            'minLength' => 1
        ];

        $userDataSchema = JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
            UserDescription::EMAIL => [
                'type' => 'string',
                'format' => 'email'
            ]
        ]);

        /* Message Registration */
        $eventMachine->registerCommand(Command::REGISTER_USER, $userDataSchema);
        $eventMachine->registerCommand(Command::CHANGE_USERNAME, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username
        ]));

        $eventMachine->registerEvent(Event::USER_WAS_REGISTERED, $userDataSchema);
        $eventMachine->registerEvent(Event::USERNAME_WAS_CHANGED, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            'oldName' => $username,
            'newName' => $username,
        ]));
    }
}
