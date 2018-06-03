<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages;

use Prooph\EventMachine\Container\ContainerChain;
use Prooph\EventMachine\Container\EventMachineContainer;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Persistence\Stream;
use Prooph\EventMachineTest\BasicTestCase;
use Prooph\EventMachineTest\CustomMessages\Stub\Aggregate\Todo;
use Prooph\EventMachineTest\CustomMessages\Stub\Descrption\TodoDescription;
use Prooph\EventMachineTest\CustomMessages\Stub\Event\TodoPosted;
use Prooph\EventMachineTest\CustomMessages\Stub\Projection\TodoProjector;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;

class CustomMessagesTest extends BasicTestCase
{
    /**
     * @test
     */
    public function it_passes_custom_messages_to_userland_code_if_registered()
    {
        $eventMachine = new EventMachine();

        $eventMachine->load(TodoDescription::class);

        $pmEvt = null;

        $eventMachine->on(TodoDescription::EVT_TODO_POSTED, function (TodoPosted $evt) use (&$pmEvt) {
            $pmEvt = $evt;
        });

        $eventMachine->watch(Stream::ofWriteModel())
            ->with('TodoProjection', TodoProjector::class);

        $todoProjector = new TodoProjector();


        $eventMachine->initialize(new EventMachineContainer($eventMachine));

        $eventMachine->bootstrapInTestMode([], [
            TodoProjector::class => $todoProjector
        ]);

        $todoId = Uuid::uuid4()->toString();

        $postTodo = $eventMachine->messageFactory()->createMessageFromArray(
            TodoDescription::CMD_POST_TODO,
            [
                'payload' => [
                    'todoId' => $todoId,
                    'text' => 'Test todo'
                ],
            ]
        );

        $eventMachine->dispatch($postTodo);

        $expectedTodo = [
            'todoId' => $todoId,
            'text' => 'Test todo'
        ];

        $recordedEvents = $eventMachine->popRecordedEventsOfTestSession();

        $this->assertCount(1, $recordedEvents);

        $this->assertEquals($expectedTodo, $recordedEvents[0]->payload());
        //Test that custom event metadata is passed along
        $this->assertEquals('test', $recordedEvents[0]->metadata()['meta']);

        $todo = $eventMachine->loadAggregateState(Todo::class, $todoId);

        $this->assertEquals([
            'todoId' => $todoId,
            'text' => 'Test todo'
        ], $todo);

        $this->assertInstanceOf(TodoPosted::class, $pmEvt);

        $eventMachine->runProjections(false);

        $this->assertInstanceOf(TodoPosted::class, $todoProjector->getLastHandledEvent());
    }
}
