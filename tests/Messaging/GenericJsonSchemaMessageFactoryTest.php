<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Messaging;

use Prooph\EventMachine\Commanding\GenericJsonSchemaCommand;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Prooph\EventMachine\JsonSchema\JustinRainbowJsonSchemaAssertion;
use Prooph\EventMachine\Messaging\GenericJsonSchemaMessageFactory;
use Prooph\EventMachine\Querying\GenericJsonSchemaQuery;
use Prooph\EventMachineTest\BasicTestCase;

class GenericJsonSchemaMessageFactoryTest extends BasicTestCase
{
    /**
     * @test
     */
    public function it_should_build_command_messages()
    {
        $createUserCommand = JsonSchema::object([
            'name' => JsonSchema::string(),
        ])->toArray();

        $commands = ['CreateUser' => $createUserCommand];

        $factory = new GenericJsonSchemaMessageFactory($commands, [], [], [], new JustinRainbowJsonSchemaAssertion());

        $message = $factory->createMessageFromArray('CreateUser', ['payload' => ['name' => 'John']]);

        $this->assertInstanceOf(GenericJsonSchemaCommand::class, $message);
        $this->assertEquals('John', $message->get('name'));
    }

    /**
     * @test
     */
    public function it_should_build_event_messages()
    {
        $userCreatedEvent = JsonSchema::object([
            'name' => JsonSchema::string(),
        ])->toArray();

        $events = ['UserCreated' => $userCreatedEvent];

        $factory = new GenericJsonSchemaMessageFactory([], $events, [], [], new JustinRainbowJsonSchemaAssertion());

        $message = $factory->createMessageFromArray('UserCreated', ['payload' => ['name' => 'John']]);

        $this->assertInstanceOf(GenericJsonSchemaEvent::class, $message);
        $this->assertEquals('John', $message->get('name'));
    }

    /**
     * @test
     */
    public function it_should_build_query_messages()
    {
        $userQuery = JsonSchema::object([
            'name' => JsonSchema::string(),
        ])->toArray();

        $queries = ['User' => $userQuery];

        $factory = new GenericJsonSchemaMessageFactory([], [], $queries, [], new JustinRainbowJsonSchemaAssertion());

        $message = $factory->createMessageFromArray('User', ['payload' => ['name' => 'John']]);

        $this->assertInstanceOf(GenericJsonSchemaQuery::class, $message);
        $this->assertEquals('John', $message->get('name'));
    }

    /**
     * @test
     */
    public function it_should_support_schema_type_refs()
    {
        $addressType = JsonSchema::object([
            'street' => JsonSchema::string(),
        ])->toArray();

        $createUserCommand = JsonSchema::object([
            'address' => JsonSchema::typeRef('Address'),
        ])->toArray();

        $commands = ['CreateUser' => $createUserCommand];
        $definitions = ['Address' => $addressType];

        $factory = new GenericJsonSchemaMessageFactory(
            $commands,
            [],
            [],
            $definitions,
            new JustinRainbowJsonSchemaAssertion()
        );

        $message = $factory->createMessageFromArray('CreateUser', ['payload' => ['address' => ['street' => 'test']]]);

        $this->assertInstanceOf(GenericJsonSchemaCommand::class, $message);
        $this->assertEquals(['street' => 'test'], $message->get('address'));
    }

    private function jsonSchemaAssertion(): JsonSchemaAssertion
    {
        return new JustinRainbowJsonSchemaAssertion();
    }
}
