<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Querying;

use React\Promise\Deferred;

/**
 * Interface AsyncResolver
 *
 * Prooph-like query resolver interface, that can handle a query
 * and resolves the passed $deffered instead of returning a result.
 *
 * @package Prooph\EventMachine\Querying
 */
interface AsyncResolver
{
    /**
     * Method is commented out. It only shows the basic idea of the expected __invoke signature
     */
    //public function __invoke(<QueryType> $query, Deferred $deferred): void;
}
