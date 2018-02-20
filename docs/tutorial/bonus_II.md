# Bonus II - Unit and Integration Tests

Unit testing the different parts of the application is easy. In most cases we have single purpose classes and
functions that can be tested without mocking.

## Testing Aggregate functions

Aggregate functions are pure which makes them easy to test. event-machine-skeleton provides some small test helpers in
`tests/BaseTestCase.php`. So if you extend from that base class you're ready to go. Add a folder `Model` in `tests`
and a class `BuildingTest` with the following content:

```php
<?php
declare(strict_types=1);

namespace AppTest\Model;

use App\Api\Command;
use App\Api\Event;
use App\Api\Payload;
use AppTest\BaseTestCase;
use Ramsey\Uuid\Uuid;
use App\Model\Building;

class BuildingTest extends BaseTestCase
{
    private $buildingId;
    private $buildingName;
    private $username;

    protected function setUp()
    {
        $this->buildingId = Uuid::uuid4()->toString();
        $this->buildingName = 'Acme Headquarters';
        $this->username = 'John';

        parent::setUp();
    }

    /**
     * @test
     */
    public function it_checks_in_a_user()
    {
        //Prepare expected aggregate state
        $state = Building\State::fromArray([
            Payload::BUILDING_ID => $this->buildingId,
            Payload::NAME => $this->buildingName
        ]);

        //Use test helper BaseTestCase::message() to construct command
        $command = $this->message(Command::CHECK_IN_USER, [
            Payload::BUILDING_ID => $this->buildingId,
            Payload::NAME => $this->username,
        ]);

        //Aggregate functions yield events which turns them into Generators (special type of an Iterator)
        $events = iterator_to_array(
            Building::checkInUser($state, $command)
        );

        //Another test helper to assert that list of recorded events contains given event
        $this->assertRecordedEvent(Event::USER_CHECKED_IN, [
            Payload::BUILDING_ID => $this->buildingId,
            Payload::NAME => $this->username
        ], $events);
    }

    /**
     * @test
     */
    public function it_detects_double_check_in()
    {
        //Prepare expected aggregate state
        $state = Building\State::fromArray([
            Payload::BUILDING_ID => $this->buildingId,
            Payload::NAME => $this->buildingName
        ]);

        $state = $state->withCheckedInUser($this->username);

        //Use test helper BaseTestCase::message() to construct command
        $command = $this->message(Command::CHECK_IN_USER, [
            Payload::BUILDING_ID => $this->buildingId,
            Payload::NAME => $this->username,
        ]);

        //Aggregate functions yield events which turns them into Generators (special type of an Iterator)
        $events = iterator_to_array(
            Building::checkInUser($state, $command)
        );

        //Another test helper to assert that list of recorded events contains given event
        $this->assertRecordedEvent(Event::DOUBLE_CHECK_IN_DETECTED, [
            Payload::BUILDING_ID => $this->buildingId,
            Payload::NAME => $this->username
        ], $events);

        //And the other way round, list should not contain event with given name
        $this->assertNotRecordedEvent(Event::USER_CHECKED_IN, $events);
    }
}

```
## Testing Projectors

Testing projectors is also easy when they use the `DocumentStore` API to manage projections. Event Machine ships with
an `InMemoryDocumentStore` implementation that works great in test cases. Here is an example:

*tests/Infrastructure/Projector/UserBuildingListTest.php*
```php
<?php

declare(strict_types=1);

namespace AppTest\Infrastructure\Projector;

use App\Api\Event;
use App\Api\Payload;
use App\Infrastructure\Projector\UserBuildingList;
use AppTest\BaseTestCase;
use Prooph\EventMachine\Persistence\DocumentStore;
use Prooph\EventMachine\Projecting\AggregateProjector;

final class UserBuildingListTest extends BaseTestCase
{
    const APP_VERSION = '0.1.0';
    const PROJECTION_NAME = 'user_building_list';
    const BUILDING_ID = '7c5f0c8a-54f2-4969-9596-b5bddc1e9421';
    const USERNAME1 = 'John';
    const USERNAME2 = 'Jane';

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var UserBuildingList
     */
    private $projector;

    protected function setUp()
    {
        parent::setUp();

        $this->documentStore = new DocumentStore\InMemoryDocumentStore();
        $this->projector = new UserBuildingList($this->documentStore);
        $this->projector->prepareForRun(self::APP_VERSION, self::PROJECTION_NAME);
    }

    /**
     * @test
     */
    public function it_manages_list_of_users_with_building_reference()
    {
        $collection = AggregateProjector::generateCollectionName(self::APP_VERSION, self::PROJECTION_NAME);

        $johnCheckedIn = $this->message(Event::USER_CHECKED_IN, [
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME1
        ]);

        $this->projector->handle(self::APP_VERSION, self::PROJECTION_NAME, $johnCheckedIn);

        $users = iterator_to_array($this->documentStore->filterDocs($collection, new DocumentStore\Filter\AnyFilter()));

        $this->assertEquals($users, [
            'John' => ['buildingId' => self::BUILDING_ID]
        ]);

        $janeCheckedIn = $this->message(Event::USER_CHECKED_IN, [
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME2
        ]);

        $this->projector->handle(self::APP_VERSION, self::PROJECTION_NAME, $janeCheckedIn);

        $users = iterator_to_array($this->documentStore->filterDocs($collection, new DocumentStore\Filter\AnyFilter()));

        $this->assertEquals($users, [
            'John' => ['buildingId' => self::BUILDING_ID],
            'Jane' => ['buildingId' => self::BUILDING_ID],
        ]);

        $johnCheckedOut = $this->message(Event::USER_CHECKED_OUT, [
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME1
        ]);

        $this->projector->handle(self::APP_VERSION, self::PROJECTION_NAME, $johnCheckedOut);

        $users = iterator_to_array($this->documentStore->filterDocs($collection, new DocumentStore\Filter\AnyFilter()));

        $this->assertEquals($users, [
            'Jane' => ['buildingId' => self::BUILDING_ID],
        ]);
    }
}

``` 

## Testing Finders

Finders can be tested the same way like projectors using the `InMemoryDocumentStore` with prefilled data.
I leave this as an exercise to you ;)

## Integration Tests

If you want to test the "whole thing" then you can make use of Event Machine's test mode. In test mode Event Machine is
set up with an `InMemoryEventStore` and an `InMemoryDocumentStore`. A special PSR-11 container ensures that all other services are mocked. 
Let's see it in action. The annotated integration test should be self explanatory.

*tests/Integration/NotifySecurityTest.php*
```php
<?php

declare(strict_types=1);

namespace AppTest\Integration;

use App\Api\Command;
use App\Api\Event;
use App\Api\Payload;
use App\Infrastructure\ServiceBus\UiExchange;
use AppTest\BaseTestCase;
use Prooph\EventMachine\Messaging\Message;

final class NotifySecurityTest extends BaseTestCase
{
    const BUILDING_ID = '7c5f0c8a-54f2-4969-9596-b5bddc1e9421';
    const BUILDING_NAME = 'Acme Headquarters';
    const USERNAME = 'John';

    private $uiExchange;

    protected function setUp()
    {
        //The BaseTestCase loads all Event Machine descriptions configured in config/autoload/global.php
        parent::setUp();

        //Mock UiExchange with an anonymous class that keeps track of the last received message
        $this->uiExchange = new class implements UiExchange {

            private $lastReceivedMessage;

            public function __invoke(Message $event): void
            {
                $this->lastReceivedMessage = $event;
            }

            public function lastReceivedMessage(): Message
            {
                return $this->lastReceivedMessage;
            }
        };
    }

    /**
     * @test
     */
    public function it_detects_double_check_in_and_notifies_security()
    {
        $this->eventMachine->bootstrapInTestMode(
            //Add history events that should have been recorded before current test scenario
            [
                $this->message(Event::BUILDING_ADDED, [
                    Payload::BUILDING_ID => self::BUILDING_ID,
                    Payload::NAME => self::BUILDING_NAME
                ]),
                $this->message(Event::USER_CHECKED_IN, [
                    Payload::BUILDING_ID => self::BUILDING_ID,
                    Payload::NAME => self::USERNAME
                ]),
            ],
            //Provide mocked services used in current test scenario, if you forget one the test will throw an exception
            //You don't have to mock the event store and document store, this is done internally
            [
                //Remember, UiExchange is our process manager that pushes events to rabbit
                //Event Machine is configured to push DoubleCheckInDetected events on to UiExchange (src/Api/Listener.php)
                UiExchange::class => $this->uiExchange
            ]
        );

        //Try to check in John twice
        $checkInJohn = $this->message(Command::CHECK_IN_USER, [
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME
        ]);

        $this->eventMachine->dispatch($checkInJohn);

        //After dispatch $this->lastPublishedEvent points to the event received by UiExchange mock
        $this->assertNotNull($this->uiExchange->lastReceivedMessage());

        $this->assertEquals(Event::DOUBLE_CHECK_IN_DETECTED, $this->uiExchange->lastReceivedMessage()->messageName());

        $this->assertEquals([
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME
        ], $this->uiExchange->lastReceivedMessage()->payload());
    }
}

```
 

