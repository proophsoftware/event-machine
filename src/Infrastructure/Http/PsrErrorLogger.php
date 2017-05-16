<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class PsrErrorLogger
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Acts as a Zend\Stratigility\Middleware\ErrorHandler::attachListener() listener
     *
     * @param \Throwable $error
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    public function __invoke(\Throwable $error, ServerRequestInterface $request, ResponseInterface $response)
    {
        $id = uniqid('request_');
        $this->logger->info('Request ('.$id.'): [' . $request->getMethod() . '] ' . $request->getUri());
        $this->logger->info('Request-Headers ('.$id.'): ' . json_encode($request->getHeaders()));
        $this->logger->info('Request-Body ('.$id.'): ' . $request->getBody());
        $this->logger->error('Error ('.$id.'): ' . $error);
    }
}
