<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Messaging;

use Prooph\Common\Messaging\Message;
use Prooph\EventMachine\Runtime\Flavour;
use Prooph\ServiceBus\Async\MessageProducer as ProophMessageProducer;
use React\Promise\Deferred;

final class MessageProducer implements ProophMessageProducer
{
    /**
     * @var Flavour
     */
    private $flavour;

    /**
     * @var
     */
    private $proophProducer;

    public function __construct(Flavour $flavour, ProophMessageProducer $producer)
    {
        $this->flavour = $flavour;
        $this->proophProducer = $producer;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(Message $message, Deferred $deferred = null): void
    {
        $message = $this->flavour->prepareNetworkTransmission($message);

        $this->proophProducer->__invoke($message, $deferred);
    }
}
