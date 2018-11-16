<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Eventing;

use Prooph\Common\Event\ActionEvent;
use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Messaging\MessageProducer;
use Prooph\EventMachine\Runtime\Flavour;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\AbstractPlugin;

final class EventConverterBusPlugin extends AbstractPlugin
{
    /**
     * @var Flavour
     */
    private $flavour;

    public function __construct(Flavour $flavour)
    {
        $this->flavour = $flavour;
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        if (! $messageBus instanceof EventBus) {
            throw new RuntimeException(__CLASS__ . ' can only be attached to a ' . EventBus::class);
        }

        $this->listenerHandlers[] = $messageBus->attach(
            EventBus::EVENT_DISPATCH,
            [$this, 'decorateListeners'],
            EventBus::PRIORITY_INVOKE_HANDLER + 100
        );
    }

    public function decorateListeners(ActionEvent $actionEvent): void
    {
        $listeners = \array_filter($actionEvent->getParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, []), function ($listener) {
            return \is_callable($listener);
        });

        $decoratedListeners = [];
        foreach ($listeners as $listener) {
            if (\is_object($listener) && $listener instanceof MessageProducer) {
                $decoratedListeners[] = $listener;
                continue;
            }

            $decoratedListeners[] = function (Message $message) use ($listener) {
                $this->flavour->callEventListener($listener, $message);
            };
        }

        $actionEvent->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, $decoratedListeners);
    }
}
