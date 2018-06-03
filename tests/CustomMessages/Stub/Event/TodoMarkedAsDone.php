<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Event;

use Prooph\Common\Messaging\DomainEvent;
use Prooph\Common\Messaging\PayloadTrait;

final class TodoMarkedAsDone extends DomainEvent
{
    use PayloadTrait;


}
