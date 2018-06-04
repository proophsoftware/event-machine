<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
            'text' => $todoPosted->text(),
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
