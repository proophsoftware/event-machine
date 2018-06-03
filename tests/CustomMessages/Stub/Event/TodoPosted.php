<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Event;

use Prooph\Common\Messaging\DomainEvent;
use Prooph\Common\Messaging\PayloadTrait;

final class TodoPosted extends DomainEvent
{
    use PayloadTrait;

    public static function with(string $todoId, string $text): TodoPosted
    {
        return new self([
            'todoId' => $todoId,
            'text' => $text
        ]);
    }

    public function todoId(): string
    {
        return $this->payload['todoId'];
    }

    public function text(): string
    {
        return $this->payload['text'];
    }
}
