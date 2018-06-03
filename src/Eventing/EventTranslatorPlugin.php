<?php

declare(strict_types=1);

namespace Prooph\EventMachine\Eventing;

use Prooph\Common\Event\ActionEvent;
use Prooph\EventMachine\Messaging\GenericJsonSchemaEvent;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\AbstractPlugin;

final class EventTranslatorPlugin extends AbstractPlugin
{
    /**
     * @var array
     */
    private $eventClassMap;

    public function __construct(array $eventClassMap)
    {
        $this->eventClassMap = $eventClassMap;
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        if(! $messageBus instanceof EventBus) {
            throw new \RuntimeException(__CLASS__ . ' can only be attached to an event bus. Got ' . get_class($messageBus));
        }

        //Hook in after routing to make sure that router uses GenericJsonSchemaEvent along with event name registered in Event Machine
        $this->listenerHandlers = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            [$this, 'onPostRoute'],
            MessageBus::PRIORITY_ROUTE + 1
        );
    }

    public function onPostRoute(ActionEvent $e): void
    {
        $evtName = $e->getParam(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if(! array_key_exists($evtName, $this->eventClassMap)) {
            return;
        }

        /** @var GenericJsonSchemaEvent $evt */
        $evt = $e->getParam(MessageBus::EVENT_PARAM_MESSAGE);

        if(! $evt instanceof GenericJsonSchemaEvent) {
            return;
        }

        $eventClass = $this->eventClassMap[$evtName];

        if(! is_callable([$eventClass, 'fromArray'])) {
            throw new \RuntimeException(sprintf(
                'Custom event class %s should have a static fromArray method',
                $eventClass
            ));
        }

        $evt = ([$eventClass, 'fromArray'])($evt->toArray());

        $e->setParam(MessageBus::EVENT_PARAM_MESSAGE, $evt);
    }
}
