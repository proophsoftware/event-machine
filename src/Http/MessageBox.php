<?php

declare(strict_types = 1);

namespace Prooph\Workshop\Http;

use Fig\Http\Message\StatusCodeInterface;
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\MiddlewareInterface;
use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\EmptyResponse;

final class MessageBox implements MiddlewareInterface
{
    /**
     * Dispatches command
     *
     * @var CommandBus
     */
    private $commandBus;

    /**
     * Dispatches event
     *
     * @var EventBus
     */
    private $eventBus;

    /**
     * Creates message depending on name
     *
     * @var MessageFactory
     */
    private $messageFactory;

    public function __construct(
        CommandBus $commandBus,
        EventBus $eventBus,
        MessageFactory $messageFactory
    ) {
        $this->commandBus = $commandBus;
        $this->eventBus = $eventBus;
        $this->messageFactory = $messageFactory;
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param RequestInterface $request
     * @param DelegateInterface $delegate
     *
     * @return ResponseInterface
     */
    public function process(RequestInterface $request, DelegateInterface $delegate)
    {
        $payload = null;
        $messageName = 'UNKNOWN';
        $response = new EmptyResponse();

        try {
            if(!$request instanceof ServerRequestInterface) {
                return $delegate->process($request);
            }

            $payload = $request->getParsedBody();

            if (is_array($payload) && isset($payload['message_name'])) {
                $messageName = $payload['message_name'];
            }

            $message = $this->messageFactory->createMessageFromArray($messageName, $payload);

            switch ($message->messageType()) {
                case Message::TYPE_COMMAND:
                    $this->commandBus->dispatch($message);

                    return $response->withStatus(StatusCodeInterface::STATUS_ACCEPTED);
                case Message::TYPE_EVENT:
                    $this->eventBus->dispatch($message);

                    return $response->withStatus(StatusCodeInterface::STATUS_ACCEPTED);
                default:
                    new \RuntimeException(
                        sprintf(
                            'Invalid message type "%s" for message "%s".',
                            $message->messageType(),
                            $messageName
                        ),
                        StatusCodeInterface::STATUS_BAD_REQUEST
                    );
            }
        } catch (\Assert\InvalidArgumentException $e) {
            throw new \RuntimeException(
                $e->getMessage(),
                StatusCodeInterface::STATUS_BAD_REQUEST,
                $e
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                sprintf('An error occurred during dispatching of message "%s"', $messageName),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }
}
