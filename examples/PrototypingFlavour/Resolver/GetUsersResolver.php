<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\PrototypingFlavour\Resolver;

use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Querying\AsyncResolver;
use React\Promise\Deferred;

final class GetUsersResolver implements AsyncResolver
{
    private $cachedUsers;

    public function __construct(array $cachedUsers)
    {
        $this->cachedUsers = $cachedUsers;
    }

    public function __invoke(Message $getUsers, Deferred $deferred): void
    {
        $usernameFilter = $getUsers->getOrDefault('username', null);
        $emailFilter = $getUsers->getOrDefault('email', null);

        $deferred->resolve(\array_filter($this->cachedUsers, function (array $user) use ($usernameFilter, $emailFilter): bool {
            return (null === $usernameFilter || $user['username'] === $usernameFilter)
                && (null === $emailFilter || $user['email'] === $emailFilter);
        }));
    }
}
