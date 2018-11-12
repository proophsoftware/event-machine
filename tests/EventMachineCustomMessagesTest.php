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
use ProophExample\FunctionalFlavour\Aggregate\UserDescription;
use ProophExample\FunctionalFlavour\Api\MessageDescription;
use ProophExample\FunctionalFlavour\ExampleCustomMessagePort;

class EventMachineCustomMessagesTest extends EventMachineTestAbstract
{
    protected function loadEventMachineDescriptions(EventMachine $eventMachine)
    {
        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(UserDescription::class);
    }

    protected function getCallInterceptor(): Flavour
    {
        return new FunctionalFlavour(new ExampleCustomMessagePort());
    }
}
