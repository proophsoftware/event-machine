<?php

declare(strict_types=1);

namespace Prooph\EventMachine\Messaging;

use Prooph\Common\Event\ActionEvent;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\AbstractPlugin;

final class MessageTranslatorPlugin extends AbstractPlugin
{
    /**
     * @var array
     */
    private $messageClassMap;

    public function __construct(array $messageClassMap)
    {
        $this->messageClassMap = $messageClassMap;
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        //Hook in after routing to make sure that router uses GenericJsonSchemaMessage along with message name registered in Event Machine
        $this->listenerHandlers = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            [$this, 'onPostRoute'],
            MessageBus::PRIORITY_ROUTE + 1
        );
    }

    public function onPostRoute(ActionEvent $e): void
    {
        $msgName = $e->getParam(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if(! array_key_exists($msgName, $this->messageClassMap)) {
            return;
        }

        /** @var GenericJsonSchemaMessage $msg */
        $msg = $e->getParam(MessageBus::EVENT_PARAM_MESSAGE);

        if(! $msg instanceof GenericJsonSchemaMessage) {
            return;
        }

        $msgClass = $this->messageClassMap[$msgName];

        if(! is_callable([$msgClass, 'fromArray'])) {
            throw new \RuntimeException(sprintf(
                'Custom message class %s should have a static fromArray method',
                $msgClass
            ));
        }

        $msg = ([$msgClass, 'fromArray'])($msg->toArray());

        $e->setParam(MessageBus::EVENT_PARAM_MESSAGE, $msg);
    }
}
