<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Commanding;

use Prooph\EventMachine\Eventing\EventRecorderDescription;
use Prooph\EventMachine\EventMachine;

final class CommandProcessorDescription
{
    /**
     * @var EventMachine
     */
    private $eventMachine;

    /**
     * @var string
     */
    private $commandName;

    /**
     * @var bool
     */
    private $createAggregate;

    /**
     * @var string
     */
    private $aggregateType;

    /**
     * @var string
     */
    private $aggregateIdentifier = 'id';

    /**
     * @var callable
     */
    private $aggregateFunction;

    private $eventRecorderMap = [];

    public function __construct(string $commandName, EventMachine $eventMachine)
    {
        $this->commandName = $commandName;
        $this->eventMachine = $eventMachine;
    }

    public function withNew(string $aggregateType, callable $aggregateFunction): self
    {
        $this->assertWithAggregateWasNotCalled();

        $this->createAggregate = true;
        $this->aggregateType = $aggregateType;
        $this->aggregateFunction = $aggregateFunction;

        return $this;
    }

    public function withExisting(string  $aggregateType, callable $aggregateFunction): self
    {
        $this->assertWithAggregateWasNotCalled();

        $this->createAggregate = false;
        $this->aggregateType = $aggregateType;
        $this->aggregateFunction = $aggregateFunction;

        return $this;
    }

    public function identifiedBy(string $aggregateIdentifier): self
    {
        if(null === $this->aggregateType) {
            throw new \BadMethodCallException("You should not call identifiedBy before calling one of the with* Aggregate methods.");
        }

        $this->aggregateIdentifier = $aggregateIdentifier;
    }

    public function recordThat(string $eventName): EventRecorderDescription
    {
        if (array_key_exists($eventName, $this->eventRecorderMap)) {
            throw new \BadMethodCallException("Method recordThat was already called for event: " . $eventName);
        }

        if(!$this->eventMachine->isKnownEvent($eventName)) {
            throw new \BadMethodCallException("Event $eventName is unknown. You should register it first.");
        }

        $this->eventRecorderMap[$eventName] = new EventRecorderDescription($eventName, $this);

        return $this->eventRecorderMap[$eventName];
    }

    private function assertWithAggregateWasNotCalled(): void
    {
        if(null !== $this->createAggregate) {
            throw new \BadMethodCallException("Method with(New|Existing) Aggregate was called twice for the same command.");
        }
    }
}
