<?php
declare(strict_types = 1);

namespace Prooph\EventMachine;

interface EventMachineDescription
{
    public static function describe(EventMachine $eventMachine): void;
}
