<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Command;

final class PostTodo
{
    private $todoId;

    private $text;

    public static function fromArray(array $genericMsgData): PostTodo
    {
        $self = new self();

        $self->todoId = (string)$genericMsgData['payload']['todoId'] ?? '';
        $self->text = (string)$genericMsgData['payload']['text'] ?? '';

        return $self;
    }

    public function todoId(): string
    {
        return $this->todoId;
    }

    public function text(): string
    {
        return $this->text;
    }
}
