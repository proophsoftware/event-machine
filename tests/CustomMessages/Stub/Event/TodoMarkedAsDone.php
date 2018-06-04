<?php
/**
 * This file is part of the proophsoftware/crm.
 * (c) 2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Event;

use Prooph\Common\Messaging\DomainEvent;
use Prooph\Common\Messaging\PayloadTrait;

final class TodoMarkedAsDone extends DomainEvent
{
    use PayloadTrait;

    public static function with(string $todoId): TodoMarkedAsDone
    {
        return new self([
            'todoId' => $todoId
        ]);
    }

    public function todoId(): string
    {
        return $this->payload['todoId'];
    }
}
