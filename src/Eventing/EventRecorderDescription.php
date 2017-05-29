<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Eventing;

use Prooph\EventMachine\Commanding\CommandProcessorDescription;

final class EventRecorderDescription
{
    /**
     * @var CommandProcessorDescription
     */
    private $commandProcessorDescription;

    /**
     * @var string
     */
    private $recordedEvent;

    /**
     * @var callable
     */
    private $applyFunction;

    public function __construct(string $eventName, CommandProcessorDescription $commandProcessorDescription)
    {
        $this->recordedEvent = $eventName;
        $this->commandProcessorDescription = $commandProcessorDescription;
    }

    public function __invoke(): array
    {
        if(null === $this->applyFunction) {
            throw new \RuntimeException("No apply function specified for event: " . $this->recordedEvent);
        }

        return [
            'event' => $this->recordedEvent,
            'apply' => $this->applyFunction,
        ];
    }

    public function apply(callable $applyFunction): CommandProcessorDescription
    {
        if (null !== $this->applyFunction) {
            throw new \BadMethodCallException("You can only assign one apply callback per recorded event");
        }

        $this->applyFunction = $applyFunction;

        return $this->commandProcessorDescription;
    }
}
