<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Descrption;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachineTest\CustomMessages\Stub\Aggregate\Todo;
use Prooph\EventMachineTest\CustomMessages\Stub\Command\PostTodo;
use Prooph\EventMachineTest\CustomMessages\Stub\Event\TodoPosted;

final class TodoDescription implements EventMachineDescription
{
    const CMD_POST_TODO = 'Test.PostTodo';

    const EVT_TODO_POSTED = 'Test.TodoPosted';
    const EVT_TODO_MAKRED_AS_DONE = 'Test.TodoMarkedAsDone';

    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->registerCommand(self::CMD_POST_TODO, JsonSchema::object([
            'todoId' => JsonSchema::uuid(),
            'text' => JsonSchema::string()
        ]), PostTodo::class);

        $eventMachine->registerEvent(self::EVT_TODO_POSTED, JsonSchema::object([
            'todoId' => JsonSchema::uuid(),
            'text' => JsonSchema::string()
        ]), TodoPosted::class);

        $eventMachine->process(self::CMD_POST_TODO)
            ->withNew(Todo::class)
            ->identifiedBy('todoId')
            ->handle([Todo::class, 'post'])
            ->recordThat(self::EVT_TODO_POSTED)
            ->apply([Todo::class, 'whenTodoPosted']);
    }
}
