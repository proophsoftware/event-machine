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

final class ReadModel
{
    /**
     * @var array
     */
    private $desc;

    /**
     * @var Stream
     */
    private $sourceStream;

    /**
     * @var Projector|CustomEventProjector
     */
    private $projector;

    /**
     * @var Flavour
     */
    private $flavour;

    /**
     * @var string
     */
    private $appVersion;

    public static function fromProjectionDescription(array $desc, Flavour $flavour, EventMachine $eventMachine): ReadModel
    {
        $projector = $eventMachine->loadProjector($desc[ProjectionDescription::PROJECTOR_SERVICE_ID]);

        return new self($desc, $projector, $flavour, $eventMachine->appVersion());
    }

    private function __construct(array $desc, $projector, Flavour $flavour, string $appVersion)
    {
        $this->desc = $desc;
        $this->sourceStream = Stream::fromArray($this->desc[ProjectionDescription::SOURCE_STREAM]);
        $this->flavour = $flavour;
        $this->projector = $projector;
        $this->appVersion = $appVersion;
    }

    public function isInterestedIn(string $sourceStreamName, Message $event): bool
    {
        if ($this->sourceStream->streamName() !== $sourceStreamName) {
            return false;
        }

        if ($this->desc[ProjectionDescription::AGGREGATE_TYPE_FILTER]) {
            $aggregateType = $event->metadata()['_aggregate_type'] ?? null;

            if (! $aggregateType) {
                return false;
            }

            if ($this->desc[ProjectionDescription::AGGREGATE_TYPE_FILTER] !== $aggregateType) {
                return false;
            }
        }

        if ($this->desc[ProjectionDescription::EVENTS_FILTER]) {
            if (! \in_array($event->messageName(), $this->desc[ProjectionDescription::EVENTS_FILTER])) {
                return false;
            }
        }

        return true;
    }

    public function prepareForRun(): void
    {
        $this->projector->prepareForRun($this->appVersion, $this->desc[ProjectionDescription::PROJECTION_NAME]);
    }

    public function handle(Message $event): void
    {
        $this->flavour->callProjector($this->projector, $this->appVersion, $this->desc[ProjectionDescription::PROJECTION_NAME], $event);
    }

    public function delete(): void
    {
        $this->projector->deleteReadModel($this->appVersion, $this->desc[ProjectionDescription::PROJECTION_NAME]);
    }
}
