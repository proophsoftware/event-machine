<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Aggregate;

use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Runtime\Flavour;

final class AggregateTestHistoryEventEnricher
{
    public static function enrichHistory(array $history, array $aggregateDefinitions, Flavour $flavour): array
    {
        $enrichedHistory = [];

        $aggregateMap = [];

        /** @var Message $event */
        foreach ($history as $event) {
            $aggregateDefinition = self::getAggregateDescriptionByEvent($event->messageName(), $aggregateDefinitions);

            if (! $aggregateDefinition) {
                throw new \InvalidArgumentException('Unable to find aggregate description for event with name: ' . $event->messageName());
            }

            $serializedEvent = $flavour->prepareNetworkTransmission($event);

            $arId = $serializedEvent->getOrDefault($aggregateDefinition['aggregateIdentifier'], null);

            if (! $arId) {
                throw new \InvalidArgumentException(\sprintf(
                    'Event with name %s does not contain an aggregate identifier. Expected key was %s',
                    $event->messageName(),
                    $aggregateDefinition['aggregateIdentifier']
                ));
            }

            $event = $event->withAddedMetadata('_aggregate_type', $aggregateDefinition['aggregateType']);
            $event = $event->withAddedMetadata('_aggregate_id', $arId);

            $aggregateMap[$aggregateDefinition['aggregateType']][$arId][] = $event;

            $event = $event->withAddedMetadata('_aggregate_version', \count($aggregateMap[$aggregateDefinition['aggregateType']][$arId]));

            $enrichedHistory[] = $event;
        }

        return $enrichedHistory;
    }

    private static function getAggregateDescriptionByEvent(string $eventName, array $aggregateDescriptions): ?array
    {
        foreach ($aggregateDescriptions as $description) {
            if (\array_key_exists($eventName, $description['eventApplyMap'])) {
                return $description;
            }
        }

        return null;
    }
}
