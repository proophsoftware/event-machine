<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Querying;

/**
 * Interface SyncResolver
 *
 * Marker interface to tell Event Machine Flavours that the query resolver is blocking and returns a value when invoked.
 *
 * @package Prooph\EventMachine\Querying
 */
interface SyncResolver
{
    /**
     * Method is commented out, because resolvers should be able to type hint a query them self.
     * It only shows the expected method signature
     */
    //public function __invoke(<QueryType> $query): <Result>;
}
