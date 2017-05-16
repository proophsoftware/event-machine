<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Aggregate;

use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventSourcing\Aggregate\Exception\RuntimeException;
use Prooph\EventSourcing\AggregateChanged;

final class GenericAggregateRoot
{
    /**
     * @var string
     */
    private $aggregateId;

    /**
     * Map with event name being the key and callable apply method for that event being the value
     *
     * @var callable[]
     */
    private $eventApplyMap;

    /**
     * @var array
     */
    private $aggregateState = [];

    /**
     * Current version
     *
     * @var int
     */
    private $version = 0;

    /**
     * List of events that are not committed to the EventStore
     *
     * @var GenericJsonSchemaEvent[]
     */
    private $recordedEvents = [];

    /**
     * @throws RuntimeException
     */
    protected static function reconstituteFromHistory(string $aggregateId, array $eventApplyMap, \Iterator $historyEvents): self
    {
        $instance = new self($aggregateId, $eventApplyMap);
        $instance->replay($historyEvents);

        return $instance;
    }

    public function __construct(string  $aggregateId, array $eventApplyMap)
    {
        $this->aggregateId = $aggregateId;
        $this->eventApplyMap = $eventApplyMap;
    }

    /**
     * Record an aggregate changed event
     */
    public function recordThat(GenericJsonSchemaEvent $event): void
    {
        if(!array_key_exists($event->messageName(), $this->eventApplyMap)) {
            throw new \RuntimeException("Wrong event recording detected. Unknown event passed to GenericAggregateRoot: " . $event->messageName());
        }

        $this->version += 1;

        $event = $event->withAddedMetadata('_aggregate_id', $this->aggregateId());
        $event = $event->withAddedMetadata('_aggregate_version', $this->version);
        $this->recordedEvents[] = $event;

        $this->apply($event);
    }

    public function currentState(): array
    {
        return $this->aggregateState;
    }

    protected function aggregateId(): string
    {
        return $this->aggregateId;
    }

    /**
     * Get pending events and reset stack
     *
     * @return AggregateChanged[]
     */
    protected function popRecordedEvents(): array
    {
        $pendingEvents = $this->recordedEvents;

        $this->recordedEvents = [];

        return $pendingEvents;
    }

    /**
     * Replay past events
     *
     * @throws RuntimeException
     */
    protected function replay(\Iterator $historyEvents): void
    {
        foreach ($historyEvents as $pastEvent) {
            /** @var GenericJsonSchemaEvent $pastEvent */
            $this->version = $pastEvent->version();

            $this->apply($pastEvent);
        }
    }

    private function apply(GenericJsonSchemaEvent $event): void
    {
        $apply = $this->eventApplyMap[$event->messageName()];

        $newArState = $apply($this->aggregateState, $event->payload());

        if(!is_array($newArState)) {
            throw new \RuntimeException("Apply function for " . $event->messageName() . " did not return aggregate state as array. Got " . gettype($newArState));
        }

        $this->aggregateState = $newArState;
    }
}
