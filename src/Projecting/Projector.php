<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Projecting;

use Prooph\EventMachine\Messaging\Message;

/**
 * Projections are rebuilt on each deployment
 *
 * A projector should always include the app version in table/collection names.
 *
 * A blue/green deployment strategy is used.
 * This means that the read model for the new app version is built during deployment.
 * The old read model remains active. In case of a rollback it is still available and can be accessed.
 *
 * The old read model is first deleted when yet another new version of the app is deployed.
 *
 * Interface Projector
 * @package Prooph\EventMachine\Projecting
 */
interface Projector
{
    public function prepareForRun(string $appVersion, string $projectionName): void;

    public function handle(string $appVersion, string $projectionName, Message $event): void;

    public function deleteReadModel(string $appVersion, string $projectionName): void;
}
