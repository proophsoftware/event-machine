<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Descrption;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachineTest\CustomMessages\Stub\Aggregate\Todo;
use Prooph\EventMachineTest\CustomMessages\Stub\Command\MarkAsDone;
use Prooph\EventMachineTest\CustomMessages\Stub\Command\PostTodo;
use Prooph\EventMachineTest\CustomMessages\Stub\Event\TodoMarkedAsDone;
use Prooph\EventMachineTest\CustomMessages\Stub\Event\TodoPosted;
use Prooph\EventMachineTest\CustomMessages\Stub\Query\GetDoneTodos;
use Prooph\EventMachineTest\CustomMessages\Stub\Query\GetTodo;
use Prooph\EventMachineTest\CustomMessages\Stub\Query\TodoFinder;

final class TodoDescription implements EventMachineDescription
{
    const CMD_POST_TODO = 'Test.PostTodo';
    const CMD_MARK_AS_DONE = 'Test.MarkAsDone';

    const EVT_TODO_POSTED = 'Test.TodoPosted';
    const EVT_TODO_MAKRED_AS_DONE = 'Test.TodoMarkedAsDone';

    const QRY_GET_TODO = 'Test.GetTodo';
    const QRY_GET_DONE_TODOS = 'Test.GetDoneTodos';

    public static function describe(EventMachine $eventMachine): void
    {
        //Custom DTOs used as messages
        $eventMachine->registerCommand(self::CMD_POST_TODO, JsonSchema::object([
            'todoId' => JsonSchema::uuid(),
            'text' => JsonSchema::string()
        ]), PostTodo::class);

        $eventMachine->registerEvent(self::EVT_TODO_POSTED, JsonSchema::object([
            'todoId' => JsonSchema::uuid(),
            'text' => JsonSchema::string()
        ]), TodoPosted::class);

        $eventMachine->registerQuery(self::QRY_GET_TODO, JsonSchema::object([
            'todoId' => JsonSchema::uuid()
        ]), GetTodo::class)
            ->resolveWith(TodoFinder::class)
            ->setReturnType(JsonSchema::object([
                'todoId' => JsonSchema::uuid(),
                'text' => JsonSchema::string()
            ]));

        $eventMachine->process(self::CMD_POST_TODO)
            ->withNew(Todo::class)
            ->identifiedBy('todoId')
            ->handle([Todo::class, 'post'])
            ->recordThat(self::EVT_TODO_POSTED)
            ->apply([Todo::class, 'whenTodoPosted']);

        //prooph messages
        $eventMachine->registerCommand(self::CMD_MARK_AS_DONE, JsonSchema::object([
            'todoId' => JsonSchema::uuid()
        ]), MarkAsDone::class);

        $eventMachine->registerEvent(self::EVT_TODO_MAKRED_AS_DONE, JsonSchema::object([
            'todoId' => JsonSchema::uuid()
        ]), TodoMarkedAsDone::class);

        $eventMachine->registerQuery(self::QRY_GET_DONE_TODOS, JsonSchema::object([
            'todoId' => JsonSchema::uuid()
        ]), GetDoneTodos::class)
            ->resolveWith(TodoFinder::class)
            ->setReturnType(JsonSchema::array(JsonSchema::uuid()));

        $eventMachine->process(self::CMD_MARK_AS_DONE)
            ->withExisting(Todo::class)
            ->handle([Todo::class, 'markAsDone'])
            ->recordThat(self::EVT_TODO_MAKRED_AS_DONE)
            ->apply([Todo::class, 'whenTodoMarkedAsDone']);
    }
}
