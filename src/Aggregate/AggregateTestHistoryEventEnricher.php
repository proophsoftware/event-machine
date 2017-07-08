<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Aggregate;

use Prooph\Common\Messaging\Message;

final class AggregateTestHistoryEventEnricher
{
    public static function enrichHistory(array $history, array $aggregateDefinitions): array
    {
        $enrichedHistory = [];

        $aggregateMap = [];

        /** @var Message $event */
        foreach ($history as $event) {
            $aggregateDefinition = self::getAggregateDescriptionByEvent($event->messageName(), $aggregateDefinitions);

            if(!$aggregateDefinition) {
                throw new \InvalidArgumentException("Unable to find aggregate description for event with name: " . $event->messageName());
            }

            $arId = $event->payload()[$aggregateDefinition['aggregateIdentifier']] ?? null;

            if(!$arId) {
                throw new \InvalidArgumentException(sprintf(
                    "Event with name %s does not contain an aggregate identifier. Expected key was %s",
                    $event->messageName(),
                    $aggregateDefinition['aggregateIdentifier']
                ));
            }

            $event = $event->withAddedMetadata('_aggregate_type', $aggregateDefinition['aggregateType']);
            $event = $event->withAddedMetadata('_aggregate_id', $arId);

            $aggregateMap[$aggregateDefinition['aggregateType']][$arId][] = $event;

            $event = $event->withAddedMetadata('_aggregate_version', count($aggregateMap[$aggregateDefinition['aggregateType']][$arId]));

            $enrichedHistory[] = $event;
        }

        return $enrichedHistory;
    }

    private static function getAggregateDescriptionByEvent(string $eventName, array $aggregateDescriptions): ?array
    {
        foreach ($aggregateDescriptions as $description) {
            if(array_key_exists($eventName, $description['eventApplyMap'])) {
                return $description;
            }
        }

        return null;
    }
}
