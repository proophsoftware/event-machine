<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Projecting;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Persistence\Stream;

final class ProjectionDescription
{
    /**
     * @var Stream
     */
    private $sourceStream;

    /**
     * @var string
     */
    private $projectionName;

    /**
     * @var string
     */
    private $projectorServiceId;

    /**
     * @var string|null
     */
    private $aggregateTypeFilter;

    /**
     * @var array|null
     */
    private $eventsFilter;

    /**
     * @var EventMachine
     */
    private $eventMachine;

    public function __construct(Stream $stream, EventMachine $eventMachine)
    {
        $this->sourceStream = $stream;
    }

    public function with(string $projectionName, string $projectorServiceId): self
    {
        if(mb_strlen($projectionName) === 0) {
            throw new \InvalidArgumentException("Projection name must not be empty");
        }

        if(mb_strlen($projectorServiceId) === 0) {
            throw new \InvalidArgumentException("Projector service id must not be empty");
        }

        if($this->eventMachine->isKnownProjection($projectionName)) {
            throw new \InvalidArgumentException("Projection $projectionName is already registered!");
        }

        $this->projectionName = $projectionName;
        $this->projectorServiceId = $projectorServiceId;

        return $this;
    }

    public function filterAggregateType(string $aggregateType): self
    {
        $this->assertWithProjectionIsCalled(__METHOD__);

        if(mb_strlen($aggregateType) === 0) {
            throw new \InvalidArgumentException("Aggregate type filter must not be empty");
        }

        $this->aggregateTypeFilter = $aggregateType;

        return $this;
    }

    public function filterEvents(array $listOfEvents): self
    {
        $this->assertWithProjectionIsCalled(__METHOD__);

        foreach ($listOfEvents as $event) {
            if(!is_string($event)) {
                throw new \InvalidArgumentException("Event filter must be a list of event names. Got a " . (is_object($event)? get_class($event) : gettype($event)));
            }
        }

        $this->eventsFilter = $listOfEvents;

        return $this;
    }

    public function __invoke()
    {
        $this->assertWithProjectionIsCalled('EventMachine::bootstrap');

        return [
            'projection_name' => $this->projectionName,
            'projector_service_id' => $this->projectorServiceId,
            'source_stream' => $this->sourceStream->toArray(),
            'aggregate_type_filter' => $this->aggregateTypeFilter,
            'events_filter' => $this->eventsFilter,
        ];
    }

    private function assertWithProjectionIsCalled(string $method): void
    {
        if(null === $this->projectionName) {
            throw new \BadMethodCallException("Method with projection was not called. You need to call it before calling $method");
        }
    }
}
