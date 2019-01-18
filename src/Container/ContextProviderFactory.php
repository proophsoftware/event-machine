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

use Prooph\EventMachine\Aggregate\ContextProvider;
use Psr\Container\ContainerInterface;

class ContextProviderFactory
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function build(string $contextProvider): ContextProvider
    {
        return $this->container->get($contextProvider);
    }
}
