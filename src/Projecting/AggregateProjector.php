<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Projecting;

use Prooph\Common\Messaging\Message;
use Prooph\EventMachine\Aggregate\Exception\AggregateNotFound;
use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Persistence\DocumentStore;

/**
 * Note: Only aggregate events of a certain aggregate type can be handled with the projector
 *
 * Example usage:
 * <code>
 * $eventMachine->watch(Stream::ofWriteModel())
 *  ->with(AggregateProjector::generateProjectionName('My.AR'), AggregateProjector::class)
 *  ->filterAggregateType('My.AR')
 *  ->storeDocuments(JsonSchema::object(...))
 * </code>
*/
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

    public static function generateProjectionName(string $aggregateType): string
    {
        return $aggregateType . '.Projection';
    }

    public static function generateCollectionName(string $appVersion, string $projectionName): string
    {
        return str_replace('.', '_', $projectionName.'_'.$appVersion);
    }

    public function __construct(DocumentStore $documentStore, EventMachine $eventMachine, DocumentStore\Index ...$indices)
    {
        $this->documentStore = $documentStore;
        $this->eventMachine = $eventMachine;
        $this->indices = $indices;
    }

    public function handle(string $appVersion, string $projectionName, Message $event): void
    {
        $aggregateId = $event->metadata()['_aggregate_id'] ?? null;

        if(!$aggregateId) {
            return;
        }

        $aggregateType = $event->metadata()['_aggregate_type'] ?? null;

        if(!$aggregateType) {
            return;
        }

        $this->assertProjectionNameMatchesWithAggregateType($projectionName, (string)$aggregateType);

        try {
            $aggregateState = $this->eventMachine->loadAggregateState((string)$aggregateType, (string)$aggregateId);
        } catch (AggregateNotFound $e) {
            return;
        }

        $this->documentStore->upsertDoc(
            $this->generateCollectionName($appVersion, $projectionName),
            (string)$aggregateId,
            $this->convertAggregateStateToArray($aggregateState)
        );
    }

    public function prepareForRun(string $appVersion, string $projectionName): void
    {
        if(!$this->documentStore->hasCollection($this->generateCollectionName($appVersion, $projectionName))) {
            $this->documentStore->addCollection($this->generateCollectionName($appVersion, $projectionName), ...$this->indices);
        }
    }

    public function deleteReadModel(string $appVersion, string $projectionName): void
    {
        $this->documentStore->dropCollection($this->generateCollectionName($appVersion, $projectionName));
    }

    private function assertProjectionNameMatchesWithAggregateType(string $projectionName, string $aggregateType): void
    {
        if($projectionName !== self::generateProjectionName($aggregateType)) {
            throw new \RuntimeException(sprintf(
                "Wrong projection name configured for %s. Should be %s but got %s",
                __CLASS__,
                self::generateProjectionName($aggregateType),
                $projectionName
            ));
        }
    }

    private function convertAggregateStateToArray($aggregateState): array
    {
        if(is_array($aggregateState)) {
            return $aggregateState;
        }

        if($aggregateState instanceof ImmutableRecord) {
            return $aggregateState->toArray();
        }

        return (array)json_decode(json_encode($aggregateState), true);
    }
}
