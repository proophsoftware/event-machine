<?php

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
