<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

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

    /**
     * @var array
     */
    private $servicesToInject;

    /**
     * @var array
     */
    private $eventRecorderMap = [];

    /**
     * @var string|null
     */
    private $contextProvider;

    public function __construct(string $commandName, EventMachine $eventMachine)
    {
        $this->commandName = $commandName;
        $this->eventMachine = $eventMachine;
    }

    public function withNew(string $aggregateType): self
    {
        $this->assertWithAggregateWasNotCalled();

        $this->createAggregate = true;
        $this->aggregateType = $aggregateType;

        return $this;
    }

    public function withExisting(string  $aggregateType): self
    {
        $this->assertWithAggregateWasNotCalled();

        $this->createAggregate = false;
        $this->aggregateType = $aggregateType;

        return $this;
    }

    public function identifiedBy(string $aggregateIdentifier): self
    {
        if (null === $this->aggregateType) {
            throw new \BadMethodCallException('You should not call identifiedBy before calling one of the with* Aggregate methods.');
        }

        $this->aggregateIdentifier = $aggregateIdentifier;

        return $this;
    }

    public function provideContext(string $contextProvider): self
    {
        $this->contextProvider = $contextProvider;

        return $this;
    }

    public function handle(callable $aggregateFunction, array $servicesToInject = []): self
    {
        $this->assertWithAggregateWasCalled(__METHOD__);

        $this->aggregateFunction = $aggregateFunction;
        $this->servicesToInject = $servicesToInject;

        return $this;
    }

    public function recordThat(string $eventName): EventRecorderDescription
    {
        if (array_key_exists($eventName, $this->eventRecorderMap)) {
            throw new \BadMethodCallException('Method recordThat was already called for event: ' . $eventName);
        }

        if (! $this->eventMachine->isKnownEvent($eventName)) {
            throw new \BadMethodCallException("Event $eventName is unknown. You should register it first.");
        }

        $this->assertWithAggregateWasCalled(__METHOD__);
        $this->assertHandleWasCalled(__METHOD__);

        $this->eventRecorderMap[$eventName] = new EventRecorderDescription($eventName, $this);

        return $this->eventRecorderMap[$eventName];
    }

    public function orRecordThat(string $eventName): EventRecorderDescription
    {
        return $this->recordThat($eventName);
    }

    public function andRecordThat(string $eventName): EventRecorderDescription
    {
        return $this->recordThat($eventName);
    }

    public function __invoke(): array
    {
        $this->assertWithAggregateWasCalled('EventMachine::bootstrap');
        $this->assertHandleWasCalled('EventMachine::bootstrap');

        $eventRecorderMap = [];

        foreach ($this->eventRecorderMap as $eventName => $desc) {
            $eventRecorderMap[$eventName] = $desc()['apply'];
        }

        return [
            'commandName' => $this->commandName,
            'createAggregate' => $this->createAggregate,
            'aggregateType' => $this->aggregateType,
            'aggregateIdentifier' => $this->aggregateIdentifier,
            'aggregateFunction' => $this->aggregateFunction,
            'servicesToInject' => $this->servicesToInject,
            'eventRecorderMap' => $eventRecorderMap,
            'streamName' => $this->eventMachine->writeModelStreamName(),
            'contextProvider' => $this->contextProvider,
        ];
    }

    private function assertWithAggregateWasCalled(string $method): void
    {
        if (null === $this->createAggregate) {
            throw new \BadMethodCallException("Method with(New|Existing) Aggregate was not called. You need to call it before calling $method");
        }
    }

    private function assertHandleWasCalled(string $method): void
    {
        if (null === $this->aggregateFunction) {
            throw new \BadMethodCallException("Method handle was not called. You need to call it before calling $method");
        }
    }

    private function assertWithAggregateWasNotCalled(): void
    {
        if (null !== $this->createAggregate) {
            throw new \BadMethodCallException('Method with(New|Existing) Aggregate was called twice for the same command.');
        }
    }
}
