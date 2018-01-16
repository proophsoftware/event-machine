<?php
declare(strict_types=1);

namespace ProophExample\Resolver;

use Prooph\Common\Messaging\Message;
use Prooph\EventMachine\EventMachine;
use ProophExample\Aggregate\Aggregate;
use React\Promise\Deferred;

final class GetUserResolver
{
    /**
     * @var EventMachine
     */
    private $eventMachine;

    public function __construct(EventMachine $eventMachine)
    {
        $this->eventMachine = $eventMachine;
    }

    public function __invoke(Message $getUser, Deferred $deferred): void
    {
        $userState = $this->eventMachine->loadAggregateState(Aggregate::USER, $getUser->payload()['userId']);

        if($userState) {
            $deferred->resolve($userState);
        } else {
            $deferred->reject(new \RuntimeException("User not found"));
        }
    }
}
