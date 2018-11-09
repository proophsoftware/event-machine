<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Aggregate;

use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Runtime\CallInterceptor;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventSourcing\Aggregate\AggregateTypeProvider;
use Prooph\EventSourcing\Aggregate\Exception\RuntimeException;
use Prooph\EventSourcing\AggregateChanged;

final class GenericAggregateRoot implements AggregateTypeProvider
{
    /**
     * @var string
     */
    private $aggregateId;

    /**
     * @var AggregateType
     */
    private $aggregateType;

    /**
     * Map with event name being the key and callable apply method for that event being the value
     *
     * @var callable[]
     */
    private $eventApplyMap;

    /**
     * @var mixed
     */
    private $aggregateState;

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
     * @var CallInterceptor
     */
    private $callInterceptor;

    /**
     * @throws RuntimeException
     */
    protected static function reconstituteFromHistory(
        string $aggregateId,
        AggregateType $aggregateType,
        array $eventApplyMap,
        CallInterceptor $callInterceptor,
        \Iterator $historyEvents
    ): self {
        $instance = new self($aggregateId, $aggregateType, $eventApplyMap, $callInterceptor);
        $instance->replay($historyEvents);

        return $instance;
    }

    public function __construct(string  $aggregateId, AggregateType $aggregateType, array $eventApplyMap, CallInterceptor $callInterceptor)
    {
        $this->aggregateId = $aggregateId;
        $this->aggregateType = $aggregateType;
        $this->eventApplyMap = $eventApplyMap;
        $this->callInterceptor = $callInterceptor;
    }

    /**
     * Record an aggregate changed event
     */
    public function recordThat(Message $event): void
    {
        if (! \array_key_exists($event->messageName(), $this->eventApplyMap)) {
            throw new \RuntimeException('Wrong event recording detected. Unknown event passed to GenericAggregateRoot: ' . $event->messageName());
        }

        $this->version += 1;

        $event = $event->withAddedMetadata('_aggregate_id', $this->aggregateId());
        $event = $event->withAddedMetadata('_aggregate_version', $this->version);
        $this->recordedEvents[] = $event;

        $this->apply($event);
    }

    public function currentState()
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

            $pastEvent = $this->callInterceptor->convertMessageReceivedFromNetwork($pastEvent, true);

            $this->apply($pastEvent);
        }
    }

    private function apply(Message $event): void
    {
        $apply = $this->eventApplyMap[$event->messageName()];

        if ($this->aggregateState === null) {
            $newArState = $this->callInterceptor->callApplyFirstEvent($apply, $event);
        } else {
            $newArState = $this->callInterceptor->callApplySubsequentEvent($apply, $this->aggregateState, $event);
        }

        if (null === $newArState) {
            throw new \RuntimeException('Apply function for ' . $event->messageName() . ' did not return a new aggregate state.');
        }

        $this->aggregateState = $newArState;
    }

    public function aggregateType(): AggregateType
    {
        return $this->aggregateType;
    }
}
