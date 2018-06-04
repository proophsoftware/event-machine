<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Projection;

use Prooph\EventMachine\Projecting\Projector;

final class TodoProjector implements Projector
{
    private $lastHandledEvent;

    public function prepareForRun(string $appVersion, string $projectionName): void
    {
        //nothing to do
    }

    public function handle(string $appVersion, string $projectionName, $event): void
    {
        $this->lastHandledEvent = $event;
    }

    public function deleteReadModel(string $appVersion, string $projectionName): void
    {
        //nothing to do
    }

    public function getLastHandledEvent()
    {
        return $this->lastHandledEvent;
    }
}
