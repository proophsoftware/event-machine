<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Messaging;

use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachineTest\BasicTestCase;

final class GenericJsonSchemaMessageTest extends BasicTestCase
{
    /**
     * @test
     */
    public function it_gets_payload_property()
    {
        $userWasRegistered = new GenericJsonSchemaEvent(
            'UserWasRegistered',
            ['username' => 'John'],
            JsonSchema::object(['username' => JsonSchema::string()])->toArray(),
            $this->getJsonSchemaAssertion()
        );

        $this->assertEquals('John', $userWasRegistered->get('username'));
    }

    /**
     * @test
     */
    public function it_throws_exception_if_payload_key_does_not_exist()
    {
        $userWasRegistered = new GenericJsonSchemaEvent(
            'UserWasRegistered',
            ['username' => 'John'],
            JsonSchema::object(['username' => JsonSchema::string()])->toArray(),
            $this->getJsonSchemaAssertion()
        );

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Message payload of UserWasRegistered does not contain a key name.');

        $userWasRegistered->get('name');
    }

    /**
     * @test
     */
    public function it_returns_default_if_payload_key_does_not_exist()
    {
        $userWasRegistered = new GenericJsonSchemaEvent(
            'UserWasRegistered',
            ['username' => 'John'],
            JsonSchema::object(['username' => JsonSchema::string()])->toArray(),
            $this->getJsonSchemaAssertion()
        );

        $this->assertEquals('Noname', $userWasRegistered->getOrDefault('name', 'Noname'));
    }
}
