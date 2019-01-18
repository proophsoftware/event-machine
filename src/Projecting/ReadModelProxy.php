<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Projecting;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Persistence\Stream;
use Prooph\EventMachine\Runtime\Flavour;
use Prooph\EventStore\Projection\AbstractReadModel;

final class ReadModelProxy extends AbstractReadModel
{
    /**
     * @var array
     */
    private $projectionDescriptions;

    /**
     * @var EventMachine
     */
    private $eventMachine;

    /**
     * @var ReadModel[]
     */
    private $readModels;

    /**
     * @var Flavour
     */
    private $flavour;

    public function __construct(
        Flavour $flavour,
        array $projectionDescriptions,
        EventMachine $eventMachine)
    {
        $this->flavour = $flavour;
        $this->projectionDescriptions = $projectionDescriptions;
        $this->eventMachine = $eventMachine;
    }

    public function handle(string $streamName, Message $event): void
    {
        foreach ($this->readModels as $readModel) {
            if ($readModel->isInterestedIn($streamName, $event)) {
                $readModel->handle($event);
            }
        }
    }

    public function init(): void
    {
        $this->readModels = [];

        foreach ($this->projectionDescriptions as $prjName => $desc) {
            $stream = Stream::fromArray($desc[ProjectionDescription::SOURCE_STREAM]);

            if ($stream->isLocalService()) {
                $readModel = ReadModel::fromProjectionDescription($desc, $this->flavour, $this->eventMachine);
                $readModel->prepareForRun();
                $this->readModels[] = $readModel;
            }
        }
    }

    public function isInitialized(): bool
    {
        return null !== $this->readModels;
    }

    public function reset(): void
    {
        $this->delete();
    }

    public function delete(): void
    {
        if (! $this->isInitialized()) {
            $this->init();
        }

        foreach ($this->readModels as $readModel) {
            $readModel->delete();
        }

        $this->readModels = null;
    }
}
