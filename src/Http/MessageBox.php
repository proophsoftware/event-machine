<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Http;

use Fig\Http\Message\StatusCodeInterface;
use Prooph\EventMachine\Data\DataConverter;
use Prooph\EventMachine\Data\ImmutableRecordDataConverter;
use Prooph\EventMachine\EventMachine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use React\Promise\Promise;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\JsonResponse;

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

    /**
     * @var DataConverter
     */
    private $dataConverter;

    public function __construct(EventMachine $eventMachine, DataConverter $dataConverter = null)
    {
        $this->eventMachine = $eventMachine;
        $this->dataConverter = $dataConverter;
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

            $result = $this->eventMachine->dispatch($message);

            if ($result instanceof Promise) {
                $response = null;
                $result->done(function ($result) use (&$response) {
                    $response = new JsonResponse($this->dataConverter()->convertDataToArray($result));
                });

                return $response;
            }

            return new EmptyResponse(StatusCodeInterface::STATUS_ACCEPTED);
        } catch (\Assert\InvalidArgumentException | \InvalidArgumentException $e) {
            throw new \RuntimeException(
                $e->getMessage(),
                StatusCodeInterface::STATUS_BAD_REQUEST,
                $e
            );
        } catch (\Throwable $e) {
            $code = StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR;

            if ($e->getCode() >= 300 && $e->getCode() <= 599) {
                $code = $e->getCode();
            }

            throw new \RuntimeException(
                sprintf('An error occurred during dispatching of message "%s"', $messageName),
                $code,
                $e
            );
        }
    }

    private function dataConverter(): DataConverter
    {
        if (null === $this->dataConverter) {
            $this->dataConverter = new ImmutableRecordDataConverter();
        }

        return $this->dataConverter;
    }
}
