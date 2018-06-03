<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Command;

use Prooph\Common\Messaging\Command;
use Prooph\Common\Messaging\PayloadTrait;

final class PostTodo extends Command
{
    use PayloadTrait;

    public function todoId(): string
    {
        return $this->payload['todoId'];
    }

    public function text(): string
    {
        return $this->payload['text'];
    }
}
