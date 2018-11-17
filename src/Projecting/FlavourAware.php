<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Projecting;

use Prooph\EventMachine\Runtime\Flavour;

/**
 * Interface FlavourAware
 *
 * When projectors are loaded using EventMacine::loadProjector()
 * and implement this interface, they get current Flavour of Event Machine injected
 *
 * @package Prooph\EventMachine\Projecting
 */
interface FlavourAware
{
    public function setFlavour(Flavour $flavour): void;
}
