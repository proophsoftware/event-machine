<?php
declare(strict_types=1);

namespace Prooph\EventMachine\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Prooph\EventMachine\EventMachine;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;

final class MessageFieldResolver implements FieldResolver
{
    /**
     * @var EventMachine
     */
    private $eventMachine;

    public function __construct(EventMachine $eventMachine)
    {
        $this->eventMachine = $eventMachine;
    }

    public function canResolve($source, array $args, ServerRequestInterface $context, ResolveInfo $info): bool
    {
        $field = $info->fieldName;

        return $this->eventMachine->isKnownQuery($field) || $this->eventMachine->isKnownCommand($field);
    }

    public function resolve($source, array $args, ServerRequestInterface $context, ResolveInfo $info): PromiseInterface
    {
        $message = $this->eventMachine->messageFactory()->createMessageFromArray($info->fieldName, [
            'payload' => $args
        ]);

        $result = $this->eventMachine->dispatch($message);

        if(null === $result) {
            return new FulfilledPromise();
        }

        return $result;
    }
}
