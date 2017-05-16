<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Event;

use Prooph\EventSourcing\AggregateChanged;
use Prooph\Workshop\Infrastructure\Util\MessageName;

class DomainEvent extends AggregateChanged
{
    public function messageName(): string
    {
        return MessageName::toMessageName(get_called_class());
    }
}
