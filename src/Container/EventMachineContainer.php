<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Container;

use Prooph\EventMachine\EventMachine;
use Psr\Container\ContainerInterface;

final class EventMachineContainer implements ContainerInterface
{
    private $eventMachine;

    private $supportedServices = [
        EventMachine::SERVICE_ID_MESSAGE_FACTORY,
        EventMachine::SERVICE_ID_JSON_SCHEMA_ASSERTION,
    ];


    public function __construct(EventMachine $eventMachine)
    {
        $this->eventMachine = $eventMachine;
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        switch ($id) {
            case EventMachine::SERVICE_ID_MESSAGE_FACTORY:
                return $this->eventMachine->messageFactory();
            case EventMachine::SERVICE_ID_JSON_SCHEMA_ASSERTION:
                return $this->eventMachine->jsonSchemaAssertion();
            default:
                throw ServiceNotFound::withServiceId($id);
        }
    }

    /**
     * @inheritdoc
     */
    public function has($id)
    {
        return in_array($id, $this->supportedServices);
    }
}
