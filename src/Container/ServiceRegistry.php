<?php

declare(strict_types=1);

namespace Prooph\EventMachine\Container;

trait ServiceRegistry
{
    /**
     * @var array
     */
    private $serviceRegistry = [];

    private function makeSingleton(string $serviceId, callable $factory) {
        if(!isset($this->serviceRegistry[$serviceId])) {
            $this->serviceRegistry[$serviceId] = $factory();
        }

        return $this->serviceRegistry[$serviceId];
    }
}
