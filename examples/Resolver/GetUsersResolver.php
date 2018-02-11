<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\Resolver;

use Prooph\Common\Messaging\Message;
use React\Promise\Deferred;

interface GetUsersResolver
{
    public function __invoke(Message $getUsers, Deferred $deferred): void;
}
