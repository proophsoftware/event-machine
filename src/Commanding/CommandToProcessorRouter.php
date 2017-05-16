<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Commanding;

use Prooph\Common\Event\ActionEvent;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\AbstractPlugin;

final class CommandToProcessorRouter extends AbstractPlugin
{
    /**
     * Map with command name being the key and CommandProcessorDescription the value
     *
     * @var CommandProcessorDescription[]
     */
    private $routingMap;

    public function __construct(array $routingMap)
    {
        $this->routingMap = $routingMap;
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            [$this, 'onRouteMessage'],
            MessageBus::PRIORITY_ROUTE
        );
    }

    public function onRouteMessage(ActionEvent $actionEvent): void
    {
        $messageName = (string) $actionEvent->getParam(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if (empty($messageName)) {
            return;
        }

        if (! isset($this->routingMap[$messageName])) {
            return;
        }

        $processorDesc = $this->routingMap[$messageName];



        $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $handler);
    }
}
