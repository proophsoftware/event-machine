<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Aggregate;

use Prooph\EventMachineTest\CustomMessages\Stub\Command\PostTodo;
use Prooph\EventMachineTest\CustomMessages\Stub\Descrption\TodoDescription;
use Prooph\EventMachineTest\CustomMessages\Stub\Event\TodoPosted;

final class Todo
{
    public static function post(PostTodo $postTodo): \Generator
    {
        yield [TodoDescription::EVT_TODO_POSTED, TodoPosted::with(
            $postTodo->todoId(),
            $postTodo->text()
        )->withAddedMetadata('meta', 'test')];
    }

    public static function whenTodoPosted(TodoPosted $todoPosted): array
    {
        return [
            'todoId' => $todoPosted->todoId(),
            'text' => $todoPosted->text()
        ];
    }
}
