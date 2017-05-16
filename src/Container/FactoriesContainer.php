<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Container;

use Psr\Container\ContainerInterface;

final class FactoriesContainer implements ContainerInterface
{
    private $factories;

    public function __construct()
    {
        $this->factories = include 'app/factories.php';
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        if(!array_key_exists($id, $this->factories)) {
            throw ServiceNotFound::withServiceId($id);
        }

        return $this->factories[$id]();
    }

    /**
     * @inheritdoc
     */
    public function has($id)
    {
        return array_key_exists($id, $this->factories);
    }
}
