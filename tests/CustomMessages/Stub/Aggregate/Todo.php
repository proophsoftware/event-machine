<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Aggregate;

use Prooph\EventMachineTest\CustomMessages\Stub\Command\MarkAsDone;
use Prooph\EventMachineTest\CustomMessages\Stub\Command\PostTodo;
use Prooph\EventMachineTest\CustomMessages\Stub\Descrption\TodoDescription;
use Prooph\EventMachineTest\CustomMessages\Stub\Event\TodoMarkedAsDone;
use Prooph\EventMachineTest\CustomMessages\Stub\Event\TodoPosted;

final class Todo
{
    public static function post(PostTodo $postTodo): \Generator
    {
        yield [TodoDescription::EVT_TODO_POSTED, TodoPosted::with(
            $postTodo->todoId(),
            $postTodo->text()
        ), ['meta' => 'test']];
    }

    public static function whenTodoPosted(TodoPosted $todoPosted): array
    {
        return [
            'todoId' => $todoPosted->todoId(),
            'text' => $todoPosted->text()
        ];
    }

    public static function markAsDone(array $state, MarkAsDone $cmd): \Generator
    {
        yield [TodoDescription::EVT_TODO_MAKRED_AS_DONE, TodoMarkedAsDone::with($cmd->todoId())->withAddedMetadata('meta', 'test')];
    }

    public static function whenTodoMarkedAsDone(array $state, TodoMarkedAsDone $markedAsDone): array
    {
        $state['done'] = true;

        return $state;
    }
}
