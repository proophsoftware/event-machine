<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\FunctionalFlavour\Resolver;

use Prooph\EventMachine\Querying\AsyncResolver;
use ProophExample\FunctionalFlavour\Query\GetUsers;
use React\Promise\Deferred;

final class GetUsersResolver implements AsyncResolver
{
    private $cachedUsers;

    public function __construct(array $cachedUsers)
    {
        $this->cachedUsers = $cachedUsers;
    }

    public function __invoke(GetUsers $getUsers, Deferred $deferred): void
    {
        $deferred->resolve(\array_filter($this->cachedUsers, function (array $user) use ($getUsers): bool {
            return (null === $getUsers->username || $user['username'] === $getUsers->username)
                && (null === $getUsers->email || $user['email'] === $getUsers->email);
        }));
    }
}
