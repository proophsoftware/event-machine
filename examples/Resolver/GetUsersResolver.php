<?php
declare(strict_types=1);

namespace ProophExample\Resolver;

use Prooph\Common\Messaging\Message;
use React\Promise\Deferred;

interface GetUsersResolver
{
    public function __invoke(Message $getUsers, Deferred $deferred): void;
}
