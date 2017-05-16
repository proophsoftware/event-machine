<?php
declare(strict_types = 1);

namespace ProophExample\Aggregate;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use ProophExample\Messaging\Command;
use ProophExample\Messaging\Event;

final class UserDescription implements EventMachineDescription
{
    const IDENTIFIER = 'userId';

    public static function describe(EventMachine $eventMachine): void
    {
        self::describeMessages($eventMachine);
        self::describeUserAggregate($eventMachine);
    }

    protected static function describeMessages(EventMachine $eventMachine): void
    {
        $userId = [
            'type' => 'string',
            'minLength' => 36
        ];

        $username = [
            'type' => 'string',
            'minLength' => 1
        ];

        $userDataSchema = [
            'type' => 'object',
            'properties' => [
                'userId' => $userId,
                'username' => $username,
                'email' => [
                    'type' => 'string',
                    'format' => 'email'
                ]
            ],
            'required' => [
                'userId',
                'username',
                'email'
            ]
        ];


        $eventMachine->registerCommand(Command::REGISTER_USER, $userDataSchema);
        $eventMachine->registerCommand(Command::CHANGE_USERNAME, [
            'type' => 'object',
            'properties' => [
                'userId' => $userId,
                'username' => $username
            ],
            'required' => ['userId', 'username']
        ]);

        $eventMachine->registerEvent(Event::USER_WAS_REGISTERED, $userDataSchema);
        $eventMachine->registerEvent(Event::USERNAME_WAS_CHANGED, [
            'type' => 'object',
            'properties' => [
                'userId' => $userId,
                'oldName' => $username,
                'newName' => $username,
            ],
            'required' => ['userId', 'oldName', 'newName']
        ]);
    }

    protected static function describeUserAggregate(EventMachine $eventMachine): void
    {
        $eventMachine->process(Command::REGISTER_USER)
            ->withNew(Aggregate::USER, function(array $userData) {
                return $userData;
            })
            ->identifiedBy(self::IDENTIFIER)
            ->recordThat(Event::USER_WAS_REGISTERED)
            ->apply(function (array $aggregateState, array $eventData) {
                return $eventData;
            });

        $eventMachine->process(Command::CHANGE_USERNAME)
            ->withExisting(Aggregate::USER, function (array $aggregateState, array $changeUsername) {
                return [
                    'userId' => $changeUsername['userId'],
                    'oldName' => $aggregateState['username'],
                    'newName' => $changeUsername['username']
                ];
            })
            ->identifiedBy(self::IDENTIFIER)
            ->recordThat(Event::USERNAME_WAS_CHANGED)
            ->apply(function (array $aggregateState, array $usernameWasChanged) {
                $aggregateState['username'] = $usernameWasChanged['newName'];
                return $aggregateState;
            });
    }

    private function __construct()
    {
        //static class only
    }
}
