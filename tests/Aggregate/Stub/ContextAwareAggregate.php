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

use Prooph\EventMachine\Messaging\Message;

final class ContextAwareAggregate
{
    public static function add(Message $addCAA, array $context): \Generator
    {
        yield ['CAAAdded', [
            'id' => $addCAA->get('id'),
            'context' => $context,
        ]];
    }

    public static function whenCAAAdded(Message $caaAdded): array
    {
        return $caaAdded->get('context');
    }

    public static function update(Message $updateCAA, array $context): \Generator
    {
        yield ['CAAUpdated', [
            'id' => $updateCAA->get('id'),
            'context' => $context,
        ]];
    }

    public static function whenCAAUpdated(Message $caaUpdated): array
    {
        return $caaUpdated->get('context');
    }
}
