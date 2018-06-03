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

use Prooph\Common\Event\ActionEvent;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Container\ContextProviderFactory;
use Prooph\EventStore\EventStore;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\AbstractPlugin;
use Prooph\SnapshotStore\SnapshotStore;

final class CommandToProcessorRouter extends AbstractPlugin
{
    /**
     * Map with command name being the key and CommandProcessorDescription the value
     *
     * @var array
     */
    private $routingMap;

    /**
     * @var array
     */
    private $aggregateDescriptions;

    /**
     * @var array
     */
    private $eventClassMap;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var ContextProviderFactory
     */
    private $contextProviderFactory;

    /**
     * @var SnapshotStore|null
     */
    private $snapshotStore;

    public function __construct(
        array $routingMap,
        array $aggregateDescriptions,
        array $eventClassMap,
        MessageFactory $messageFactory,
        EventStore $eventStore,
        ContextProviderFactory $providerFactory,
        SnapshotStore $snapshotStore = null
    ) {
        $this->routingMap = $routingMap;
        $this->aggregateDescriptions = $aggregateDescriptions;
        $this->eventClassMap = $eventClassMap;
        $this->messageFactory = $messageFactory;
        $this->eventStore = $eventStore;
        $this->contextProviderFactory = $providerFactory;
        $this->snapshotStore = $snapshotStore;
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

        $aggregateDesc = $this->aggregateDescriptions[$processorDesc['aggregateType'] ?? ''] ?? [];

        if (! isset($aggregateDesc['eventApplyMap'])) {
            throw new \RuntimeException('Missing eventApplyMap for aggregate type: ' . $processorDesc['aggregateType'] ?? '');
        }

        if (! isset($processorDesc['contextProvider'])) {
            $processorDesc['contextProvider'] = null;
        }

        $processorDesc['eventApplyMap'] = $aggregateDesc['eventApplyMap'];
        $processorDesc['eventClassMap'] = $this->eventClassMap;

        $contextProvider = $processorDesc['contextProvider'] ? $this->contextProviderFactory->build($processorDesc['contextProvider']) : null;

        $commandProcessor = CommandProcessor::fromDescriptionArrayAndDependencies(
            $processorDesc,
            $this->messageFactory,
            $this->eventStore,
            $this->snapshotStore,
            $contextProvider
        );

        $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $commandProcessor);
    }
}
