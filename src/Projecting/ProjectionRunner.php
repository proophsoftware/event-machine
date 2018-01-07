<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Projecting;

use Prooph\Common\Messaging\Message;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Persistence\Stream;
use Prooph\EventStore\Projection\ProjectionManager;
use Prooph\EventStore\Projection\ReadModelProjector;

final class ProjectionRunner
{
    public const EVENT_MACHINE_PROJECTION = 'event_machine_projection';

    /**
     * @var ReadModelProjector
     */
    private $projection;

    /**
     * @var bool
     */
    private $testMode;

    public static function eventMachineProjectionName(string $appVersion): string
    {
        return self::EVENT_MACHINE_PROJECTION . '_' . str_replace('.', '_', $appVersion);
    }

    public function __construct(
        ProjectionManager $projectionManager,
        array $projectionDescriptions,
        EventMachine $eventMachine,
        array $projectionOptions = null)
    {
        if(null === $projectionOptions) {
            $projectionOptions = [
                ReadModelProjector::OPTION_PERSIST_BLOCK_SIZE => 1
            ];
        }

        $this->testMode = $eventMachine->isTestMode();

        $sourceStreams = [];

        foreach ($projectionDescriptions as $prjName => $description) {
            $sourceStream = Stream::fromArray($description[ProjectionDescription::SOURCE_STREAM]);

            if($sourceStream->isLocalService()) {
                $sourceStreams[$sourceStream->streamName()] = null;
            }
        }

        $sourceStreams = array_keys($sourceStreams);

        $totalSourceStreams = count($sourceStreams);

        if($totalSourceStreams === 0) {
            return;
        }

        $this->projection = $projectionManager->createReadModelProjection(
            self::eventMachineProjectionName($eventMachine->appVersion()),
            new ReadModelProxy(
                $projectionDescriptions,
                $eventMachine
            ),
            $projectionOptions
        );

        if($totalSourceStreams === 1) {
            $this->projection->fromStream($sourceStreams[0]);
        } else {
            $this->projection->fromStreams(...$sourceStreams);
        }

        $this->projection->whenAny(function ($state, Message $event) {
            $this->readModel()->stack('handle', $this->streamName(), $event);
        });
    }

    public function run(bool $keepRunning, array $options = null): void
    {
        $this->projection->run(!$this->testMode && $keepRunning);
    }
}
