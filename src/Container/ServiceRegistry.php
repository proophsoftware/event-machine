<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Container;

trait ServiceRegistry
{
    /**
     * @var array
     */
    private $serviceRegistry = [];

    private function makeSingleton(string $serviceId, callable $factory)
    {
        if (! isset($this->serviceRegistry[$serviceId])) {
            $this->serviceRegistry[$serviceId] = $factory();
        }

        return $this->serviceRegistry[$serviceId];
    }
}
