<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Aggregate\Stub;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;

final class ContextAwareAggregateDescription implements EventMachineDescription
{
    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->registerCommand('AddCAA', JsonSchema::object(['id' => JsonSchema::integer()]));
        $eventMachine->registerCommand('UpdateCAA', JsonSchema::object(['id' => JsonSchema::integer()]));

        $eventMachine->registerEvent('CAAAdded', JsonSchema::object([
            'id' => JsonSchema::integer(),
            'context' => JsonSchema::object(['msg' => JsonSchema::string()]),
        ]));
        $eventMachine->registerEvent('CAAUpdated', JsonSchema::object([
            'id' => JsonSchema::integer(),
            'context' => JsonSchema::object(['msg' => JsonSchema::string()]),
        ]));

        $eventMachine->process('AddCAA')
            ->withNew('CAA')
            ->provideContext('MsgContextProvider')
            ->handle([ContextAwareAggregate::class, 'add'])
            ->recordThat('CAAAdded')
            ->apply([ContextAwareAggregate::class, 'whenCAAAdded']);

        $eventMachine->process('UpdateCAA')
            ->withExisting('CAA')
            ->provideContext('MsgContextProvider')
            ->handle([ContextAwareAggregate::class, 'update'])
            ->recordThat('CAAUpdated')
            ->apply([ContextAwareAggregate::class, 'whenCAAUpdated']);
    }
}
