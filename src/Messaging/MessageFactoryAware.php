<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Messaging;

interface MessageFactoryAware
{
    public function setMessageFactory(MessageFactory $messageFactory): void;
}
