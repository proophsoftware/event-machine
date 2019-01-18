<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Querying;

use Prooph\Common\Event\ActionEvent;
use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Runtime\Flavour;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\AbstractPlugin;
use Prooph\ServiceBus\QueryBus;
use React\Promise\Deferred;

final class QueryConverterBusPlugin extends AbstractPlugin
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
        if (! $messageBus instanceof QueryBus) {
            throw new RuntimeException(__CLASS__ . ' can only be attached to a ' . QueryBus::class);
        }

        $this->listenerHandlers[] = $messageBus->attach(
            QueryBus::EVENT_DISPATCH,
            [$this, 'decorateResolver'],
            QueryBus::PRIORITY_INVOKE_HANDLER + 100
        );
    }

    public function decorateResolver(ActionEvent $actionEvent): void
    {
        $resolver = $actionEvent->getParam(QueryBus::EVENT_PARAM_MESSAGE_HANDLER);

        $actionEvent->setParam(QueryBus::EVENT_PARAM_MESSAGE_HANDLER, function (Message $query, Deferred $deferred) use ($resolver): void {
            $this->flavour->callQueryResolver($resolver, $query, $deferred);
        });
    }
}
