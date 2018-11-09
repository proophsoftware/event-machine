<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Runtime\OOP;

final class AggregateAndEventBag
{
    /**
     * @var mixed
     */
    private $aggregate;

    /**
     * @var mixed
     */
    private $event;

    public function __construct($aggregate, $event)
    {
        $this->aggregate = $aggregate;
        $this->event = $event;
    }

    /**
     * @return mixed
     */
    public function aggregate()
    {
        return $this->aggregate;
    }

    /**
     * @return mixed
     */
    public function event()
    {
        return $this->event;
    }
}
