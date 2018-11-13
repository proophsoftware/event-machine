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

use Prooph\EventMachine\Aggregate\Exception\AggregateNotFound;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Persistence\DeletableState;
use Prooph\EventMachine\Persistence\DocumentStore;
use Prooph\EventMachine\Runtime\Flavour;
use Prooph\EventMachine\Runtime\PrototypingFlavour;

final class AggregateProjector implements Projector
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var EventMachine
     */
    private $eventMachine;

    /**
     * @var DocumentStore\Index[]
     */
    private $indices;

    /**
     * @var Flavour
     */
    private $flavour;

    public static function aggregateCollectionName(string $appVersion, string $aggregateType): string
    {
        return self::generateCollectionName($appVersion, self::generateProjectionName($aggregateType));
    }

    public static function generateProjectionName(string $aggregateType): string
    {
        return $aggregateType . '.Projection';
    }

    public static function generateCollectionName(string $appVersion, string $projectionName): string
    {
        return \str_replace('.', '_', $projectionName.'_'.$appVersion);
    }

    public function __construct(DocumentStore $documentStore, EventMachine $eventMachine, DocumentStore\Index ...$indices)
    {
        $this->documentStore = $documentStore;
        $this->eventMachine = $eventMachine;
        $this->indices = $indices;
    }

    /**
     * @TODO Turn Flavour into constructor argument for Event Machine 2.0
     *
     * It's not a constructor argument due to BC
     *
     * @param Flavour $flavour
     */
    public function setFlavour(Flavour $flavour): void
    {
        if (null !== $this->flavour) {
            throw new RuntimeException('Cannot set another Flavour for ' . __CLASS__ . '. A flavour was already set bevor.');
        }

        $this->flavour = $flavour;
    }

    private function flavour(): Flavour
    {
        if (null === $this->flavour) {
            $this->flavour = new PrototypingFlavour();
        }

        return $this->flavour;
    }

    public function handle(string $appVersion, string $projectionName, $event): void
    {
        if (! $event instanceof Message) {
            throw new RuntimeException(__METHOD__ . ' can only handle events of type: ' . Message::class);
        }

        $aggregateId = $event->metadata()['_aggregate_id'] ?? null;

        if (! $aggregateId) {
            return;
        }

        $aggregateType = $event->metadata()['_aggregate_type'] ?? null;

        if (! $aggregateType) {
            return;
        }

        $this->assertProjectionNameMatchesWithAggregateType($projectionName, (string) $aggregateType);

        try {
            $aggregateState = $this->eventMachine->loadAggregateState((string) $aggregateType, (string) $aggregateId);
        } catch (AggregateNotFound $e) {
            return;
        }

        if ($aggregateState instanceof DeletableState && $aggregateState->deleted()) {
            $this->documentStore->deleteDoc(
                $this->generateCollectionName($appVersion, $projectionName),
                (string) $aggregateId
            );

            return;
        }

        $this->documentStore->upsertDoc(
            $this->generateCollectionName($appVersion, $projectionName),
            (string) $aggregateId,
            $this->flavour()->convertAggregateStateToArray($aggregateState)
        );
    }

    public function prepareForRun(string $appVersion, string $projectionName): void
    {
        if (! $this->documentStore->hasCollection($this->generateCollectionName($appVersion, $projectionName))) {
            $this->documentStore->addCollection($this->generateCollectionName($appVersion, $projectionName), ...$this->indices);
        }
    }

    public function deleteReadModel(string $appVersion, string $projectionName): void
    {
        if ($this->documentStore->hasCollection(self::generateCollectionName($appVersion, $projectionName))) {
            $this->documentStore->dropCollection(self::generateCollectionName($appVersion, $projectionName));
        }
    }

    private function assertProjectionNameMatchesWithAggregateType(string $projectionName, string $aggregateType): void
    {
        if ($projectionName !== self::generateProjectionName($aggregateType)) {
            throw new \RuntimeException(\sprintf(
                'Wrong projection name configured for %s. Should be %s but got %s',
                __CLASS__,
                self::generateProjectionName($aggregateType),
                $projectionName
            ));
        }
    }
}
