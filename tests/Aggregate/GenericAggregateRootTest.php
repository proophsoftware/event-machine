<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Aggregate;

use Prooph\Common\Messaging\Message;
use Prooph\EventMachine\Aggregate\ClosureAggregateTranslator;
use Prooph\EventMachine\Aggregate\GenericAggregateRoot;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachineTest\BasicTestCase;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Ramsey\Uuid\Uuid;

class GenericAggregateRootTest extends BasicTestCase
{
    /**
     * @test
     */
    public function it_records_events_and_can_be_reconstituted_by_them()
    {
        $eventApplyMap = [
            'UserWasRegistered' => function (Message $userWasRegistered) {
                $arState['username'] = $userWasRegistered->payload()['username'];

                return $arState;
            },
            'UsernameWasChanged' => function (array $arState, Message $usernameWasChanged) {
                $arState['username'] = $usernameWasChanged->payload()['newUsername'];

                return $arState;
            },
        ];

        $arId = Uuid::uuid4()->toString();

        $user = new GenericAggregateRoot($arId, AggregateType::fromString('User'), $eventApplyMap);

        $userWasRegistered = new GenericJsonSchemaEvent(
            'UserWasRegistered',
            ['username' => 'John'],
            JsonSchema::object(['username' => JsonSchema::string()])->toArray(),
            $this->getJsonSchemaAssertion()
        );

        $user->recordThat($userWasRegistered);

        $usernameWasChanged = new GenericJsonSchemaEvent(
            'UsernameWasChanged',
            ['oldUsername' => 'John', 'newUsername' => 'Max'],
            JsonSchema::object(['oldUsername' => JsonSchema::string(), 'newUsername' => JsonSchema::string()])->toArray(),
            $this->getJsonSchemaAssertion()
        );

        $user->recordThat($usernameWasChanged);

        self::assertEquals(['username' => 'Max'], $user->currentState());

        $recordedEvents = $this->extractRecordedEvents($user);

        self::assertCount(2, $recordedEvents);

        $translator = new ClosureAggregateTranslator($arId, $eventApplyMap);

        $sameUser = $translator->reconstituteAggregateFromHistory(AggregateType::fromString('User'), new \ArrayIterator([$recordedEvents[0]]));

        self::assertEquals(['username' => 'John'], $sameUser->currentState());

        $translator->replayStreamEvents($sameUser, new \ArrayIterator([$recordedEvents[1]]));

        self::assertEquals(['username' => 'Max'], $sameUser->currentState());
    }
}
