<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Messaging;

use Prooph\Common\Messaging\Message;
use Prooph\EventMachine\Runtime\CallInterceptor;
use Prooph\ServiceBus\Async\MessageProducer as ProophMessageProducer;
use React\Promise\Deferred;

final class MessageProducer implements ProophMessageProducer
{
    /**
     * @var CallInterceptor
     */
    private $callInterceptor;

    /**
     * @var
     */
    private $proophProducer;

    public function __construct(CallInterceptor $callInterceptor, ProophMessageProducer $producer)
    {
        $this->callInterceptor = $callInterceptor;
        $this->proophProducer = $producer;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(Message $message, Deferred $deferred = null): void
    {
        $message = $this->callInterceptor->prepareNetworkTransmission($message);

        $this->proophProducer->__invoke($message, $deferred);
    }
}
