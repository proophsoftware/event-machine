<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Command;

final class PostTodo
{
    private $todoId;

    private $text;

    public static function fromArray(array $genericMsgData): PostTodo
    {
        $self = new self();

        $self->todoId = (string) $genericMsgData['payload']['todoId'] ?? '';
        $self->text = (string) $genericMsgData['payload']['text'] ?? '';

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
