<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\OopFlavour;

use Prooph\EventMachine\Exception\InvalidArgumentException;
use Prooph\EventMachine\Runtime\Oop\Port;
use Prooph\EventMachine\Util\DetermineVariableType;
use ProophExample\FunctionalFlavour\Command\ChangeUsername;
use ProophExample\OopFlavour\Aggregate\User;

final class ExampleOopPort implements Port
{
    use DetermineVariableType;

    /**
     * {@inheritdoc}
     */
    public function callAggregateFactory(string $aggregateType, callable $aggregateFactory, $customCommand, $context = null)
    {
        return $aggregateFactory($customCommand, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function callAggregateWithCommand($aggregate, $customCommand, $context = null): void
    {
        switch (\get_class($customCommand)) {
            case ChangeUsername::class:
                /** @var User $aggregate */
                $aggregate->changeName($customCommand);
                break;
            default:
                throw new InvalidArgumentException('Unknown command: ' . self::getType($customCommand));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function popRecordedEvents($aggregate): array
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        return $aggregate->popRecordedEvents();
    }

    /**
     * {@inheritdoc}
     */
    public function applyEvent($aggregate, $customEvent): void
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        $aggregate->apply($customEvent);
    }

    /**
     * {@inheritdoc}
     */
    public function serializeAggregate($aggregate): array
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        return $aggregate->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function reconstituteAggregate(string $aggregateType, iterable $events)
    {
        switch ($aggregateType) {
            case User::TYPE:
                return User::reconstituteFromHistory($events);
                break;
            default:
                throw new InvalidArgumentException("Unknown aggregate type $aggregateType");
        }
    }
}
