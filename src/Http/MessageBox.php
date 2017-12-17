<?php

declare(strict_types=1);

namespace Prooph\EventMachine\Http;

use Fig\Http\Message\StatusCodeInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Prooph\EventMachine\EventMachine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Zend\Diactoros\Response\EmptyResponse;

/**
 * One middleware for all commands and events
 *
 * This class handles event, command and query messages depending on given request body data.
 */
final class MessageBox implements RequestHandlerInterface
{
    /**
     * @var EventMachine
     */
    private $eventMachine;

    public function __construct(EventMachine $eventMachine)
    {
        $this->eventMachine = $eventMachine;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $payload = null;
        $messageName = 'UNKNOWN';

        try {
            $payload = $request->getParsedBody();

            if (is_array($payload) && isset($payload['message_name'])) {
                $messageName = $payload['message_name'];
            }

            $messageName = $request->getAttribute('message_name', $messageName);

            $payload['message_name'] = $messageName;

            if (! isset($payload['uuid'])) {
                $payload['uuid'] = Uuid::uuid4();
            }

            if (! isset($payload['created_at'])) {
                $payload['created_at'] = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            }

            if (! isset($payload['metadata'])) {
                $payload['metadata'] = [];
            }

            $message = $this->eventMachine->messageFactory()->createMessageFromArray($messageName, $payload);

            $this->eventMachine->dispatch($message);

            return new EmptyResponse(StatusCodeInterface::STATUS_ACCEPTED);
        } catch (\Assert\InvalidArgumentException | \InvalidArgumentException $e) {
            throw new \RuntimeException(
                $e->getMessage(),
                StatusCodeInterface::STATUS_BAD_REQUEST,
                $e
            );
        } catch (\Throwable $e) {
            $code = StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR;

            if($e->getCode() >= 300 && $e->getCode() <= 599) {
                $code = $e->getCode();
            }

            throw new \RuntimeException(
                sprintf('An error occurred during dispatching of message "%s"', $messageName),
                $code,
                $e
            );
        }
    }
}
