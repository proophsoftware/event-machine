<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Runtime\Flavour;
use Prooph\EventMachine\Runtime\FunctionalFlavour;
use Prooph\EventMachine\Runtime\OopFlavour;
use ProophExample\FunctionalFlavour\Api\MessageDescription;
use ProophExample\FunctionalFlavour\ExampleCustomMessagePort;
use ProophExample\OopFlavour\Aggregate\UserDescription;
use ProophExample\OopFlavour\ExampleOOPPort;

class EventMachineOopFlavourTest extends EventMachineTestAbstract
{
    protected function loadEventMachineDescriptions(EventMachine $eventMachine)
    {
        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(UserDescription::class);
    }

    protected function getFlavour(): Flavour
    {
        return new OopFlavour(
            new ExampleOOPPort(),
            new FunctionalFlavour(new ExampleCustomMessagePort())
        );
    }
}
