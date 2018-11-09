<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Http;

use Prooph\Common\Event\ProophActionEventEmitter;
use Prooph\EventMachine\Container\ContainerChain;
use Prooph\EventMachine\Container\EventMachineContainer;
use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\Http\MessageBox;
use Prooph\EventMachineTest\BasicTestCase;
use Prooph\EventStore\ActionEventEmitterEventStore;
use Prooph\EventStore\EventStore;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\QueryBus;
use ProophExample\Standard\Aggregate\CacheableUserDescription;
use ProophExample\Standard\Messaging\MessageDescription;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

final class MessageBoxTest extends BasicTestCase
{
    /**
     * @var EventMachine
     */
    private $eventMachine;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var ActionEventEmitterEventStore
     */
    private $actionEventEmitterEventStore;

    /**
     * @var ContainerInterface
     */
    private $appContainer;

    /**
     * @var CommandBus
     */
    private $commandBus;

    /**
     * @var EventBus
     */
    private $eventBus;

    /**
     * @var QueryBus
     */
    private $queryBus;

    /**
     * @var ContainerChain
     */
    private $containerChain;

    protected function setUp()
    {
        parent::setUp();

        $this->eventMachine = new EventMachine();

        $this->eventMachine->load(MessageDescription::class);
        $this->eventMachine->load(CacheableUserDescription::class);

        $this->eventStore = $this->prophesize(EventStore::class);

        $this->actionEventEmitterEventStore = new ActionEventEmitterEventStore(
            $this->eventStore->reveal(),
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );
        $this->commandBus = new CommandBus();
        $this->eventBus = new EventBus();
        $this->queryBus = new QueryBus();

        $this->appContainer = $this->prophesize(ContainerInterface::class);

        $self = $this;
        $this->appContainer->has(EventMachine::SERVICE_ID_EVENT_STORE)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_EVENT_STORE)->will(function ($args) use ($self) {
            return $self->actionEventEmitterEventStore;
        });

        $this->appContainer->has(EventMachine::SERVICE_ID_COMMAND_BUS)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_COMMAND_BUS)->will(function ($args) use ($self) {
            return $self->commandBus;
        });

        $this->appContainer->has(EventMachine::SERVICE_ID_EVENT_BUS)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_EVENT_BUS)->will(function ($args) use ($self) {
            return $self->eventBus;
        });

        $this->appContainer->has(EventMachine::SERVICE_ID_QUERY_BUS)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_QUERY_BUS)->will(function ($args) use ($self) {
            return $self->queryBus;
        });

        $this->appContainer->has(EventMachine::SERVICE_ID_MESSAGE_FACTORY)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_JSON_SCHEMA_ASSERTION)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_SNAPSHOT_STORE)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_ASYNC_EVENT_PRODUCER)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_DOCUMENT_STORE)->willReturn(false);
        $this->appContainer->has(EventMachine::SERVICE_ID_CALL_INTERCEPTOR)->willReturn(false);

        $this->containerChain = new ContainerChain(
            $this->appContainer->reveal(),
            new EventMachineContainer($this->eventMachine)
        );
    }

    protected function tearDown()
    {
        $this->eventMachine = null;
        $this->eventStore = null;
        $this->commandBus = null;
        $this->eventBus = null;
        $this->queryBus = null;
        $this->appContainer = null;
    }

    /**
     * @test
     */
    public function it_accepts_and_processes_valid_request_over_message_box()
    {
        $this->eventMachine->initialize($this->containerChain);
        $this->eventMachine->bootstrap();

        $messageBox = new MessageBox($this->eventMachine);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute('message_name', 'RegisterUser')->willReturn('RegisterUser');
        $request->getParsedBody()->willReturn([
            'message_name' => 'RegisterUser',
            'payload' => [
                'userId' => Uuid::uuid4()->toString(),
                'username' => 'Martin',
                'email' => 'test@test.com',
            ],
        ]);

        $response = $messageBox->handle($request->reveal());
        $this->assertEquals(202, $response->getStatusCode());
        $this->assertEquals('', $response->getBody()->getContents());
    }

    /**
     * @test
     */
    public function it_rejects_request_with_unknown_message_over_message_box()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown message received. Got message with name: RegisterUserInvalid');

        $this->eventMachine->initialize($this->containerChain);
        $this->eventMachine->bootstrap();

        $messageBox = new MessageBox($this->eventMachine);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute('message_name', 'RegisterUserInvalid')->willReturn('RegisterUserInvalid');
        $request->getParsedBody()->willReturn([
            'message_name' => 'RegisterUserInvalid',
            'payload' => [
                'userId' => Uuid::uuid4()->toString(),
                'username' => 'Martin',
            ],
        ]);

        $response = $messageBox->handle($request->reveal());
    }
}
