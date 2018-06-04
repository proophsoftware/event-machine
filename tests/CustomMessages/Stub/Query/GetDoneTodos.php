<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Query;

use Prooph\Common\Messaging\PayloadTrait;
use Prooph\Common\Messaging\Query;

final class GetDoneTodos extends Query
{
    use PayloadTrait;

    public function todoId(): string
    {
        return $this->payload['todoId'];
    }
}
