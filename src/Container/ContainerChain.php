<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Container;

use Psr\Container\ContainerInterface;

final class ContainerChain implements ContainerInterface
{
    /**
     * @var ContainerInterface
     */
    private $chain = [];

    public function __construct(ContainerInterface ...$chain)
    {
        if (! count($chain)) {
            throw new \InvalidArgumentException('At least one container should be passed to container chain');
        }

        $this->chain = $chain;
    }

    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        foreach ($this->chain as $container) {
            if ($container->has($id)) {
                return $container->get($id);
            }
        }

        //Let first container throw a NotFoundExceptionInterface
        return $this->chain[0]->get($id);
    }

    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
        foreach ($this->chain as $container) {
            if ($container->has($id)) {
                return true;
            }
        }

        return false;
    }
}
