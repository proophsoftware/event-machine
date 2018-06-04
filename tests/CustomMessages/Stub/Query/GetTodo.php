<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Query;

final class GetTodo
{
    private $todoId;

    public static function fromArray(array $genericMsgData): GetTodo
    {
        $self = new self();
        $self->todoId = (string)$genericMsgData['payload']['todoId'] ?? '';
        return $self;
    }

    public function todoId(): string
    {
        return $this->todoId;
    }
}
