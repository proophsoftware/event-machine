<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Container;

use Codeliner\ArrayReader\ArrayReader;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Container\ReflectionBasedContainer;
use Prooph\EventMachine\Container\ServiceRegistry;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\JsonSchemaAssertion;
use Prooph\EventMachine\JsonSchema\JustinRainbowJsonSchemaAssertion;
use Prooph\EventMachine\Messaging\GenericJsonSchemaMessageFactory;
use Prooph\EventMachineTest\BasicTestCase;
use ProophExample\Aggregate\UserDescription;
use ProophExample\Messaging\Command;

final class ReflectionBasedContainerTest extends BasicTestCase
{
    private $serviceFactory;

    /**
     * @var ReflectionBasedContainer
     */
    private $reflectionBasedContainer;

    protected function setUp()
    {
        $this->serviceFactory = $this->buildServiceFactory([
            'event_machine' => [
                'command_map' => [
                    Command::REGISTER_USER => JsonSchema::object([
                        UserDescription::IDENTIFIER => JsonSchema::string()->withMinLength(2),
                    ])->toArray(),
                ],
                'event_map' => [],
            ],
        ]);

        $this->reflectionBasedContainer = new ReflectionBasedContainer($this->serviceFactory, [
            'CommandFactory' => MessageFactory::class,
        ]);
    }

    /**
     * @test
     */
    public function it_scans_service_factory_to_identify_factory_methods()
    {
        $serviceFactoryMap = $this->reflectionBasedContainer->getServiceFactoryMap();

        $this->assertEquals([
            JsonSchemaAssertion::class => 'jsonSchemaAssertion',
            MessageFactory::class => 'messageFactory',
        ], $serviceFactoryMap);
    }

    /**
     * @test
     */
    public function it_uses_service_factory_method_to_get_service()
    {
        $this->assertTrue($this->reflectionBasedContainer->has(MessageFactory::class));

        /** @var MessageFactory $messageFactory */
        $messageFactory = $this->reflectionBasedContainer->get(MessageFactory::class);

        $this->assertInstanceOf(GenericJsonSchemaMessageFactory::class, $messageFactory);

        //Test if config is passed correctly to message factory by checking that validation fails!
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation of RegisterUser failed: [userId] Must be at least 2 characters long');

        $command = $messageFactory->createMessageFromArray(Command::REGISTER_USER, [
            'payload' => [UserDescription::IDENTIFIER => '1'],
        ]);
    }

    /**
     * @test
     */
    public function it_uses_alias_to_get_service()
    {
        $this->assertTrue($this->reflectionBasedContainer->has('CommandFactory'));

        /** @var MessageFactory $messageFactory */
        $messageFactory = $this->reflectionBasedContainer->get('CommandFactory');

        $this->assertInstanceOf(GenericJsonSchemaMessageFactory::class, $messageFactory);
    }

    /**
     * @test
     */
    public function it_does_not_scan_service_factory_if_service_factory_map_is_provided()
    {
        $container = new ReflectionBasedContainer($this->serviceFactory, [
            'CommandFactory' => MessageFactory::class,
        ], [
            JsonSchemaAssertion::class => 'jsonSchemaAssertion',
        ]);

        //Container should not see message factory factory method because it is missing in the cached map
        $this->assertFalse($container->has('CommandMap'));
        $this->assertFalse($container->has(MessageFactory::class));
        $this->assertTrue($container->has(JsonSchemaAssertion::class));

        $assertion = $container->get(JsonSchemaAssertion::class);

        $this->assertInstanceOf(JsonSchemaAssertion::class, $assertion);
    }

    private function buildServiceFactory(array $appConfig)
    {
        return new class($appConfig) {
            use ServiceRegistry;

            /**
             * @var ArrayReader
             */
            private $appConfig;

            public function __construct(array $appConfig)
            {
                $this->appConfig = new ArrayReader($appConfig);
            }

            public function jsonSchemaAssertion(): JsonSchemaAssertion
            {
                return $this->makeSingleton(JsonSchemaAssertion::class, function () {
                    return new JustinRainbowJsonSchemaAssertion();
                });
            }

            public function messageFactory(): MessageFactory
            {
                return $this->makeSingleton(MessageFactory::class, function () {
                    return new GenericJsonSchemaMessageFactory(
                        $this->appConfig->arrayValue('event_machine.command_map', []),
                        $this->appConfig->arrayValue('event_machine.event_map', []),
                        $this->appConfig->arrayValue('event_machine.query_map', []),
                        $this->appConfig->arrayValue('event_machine.definitions', []),
                        $this->jsonSchemaAssertion()
                    );
                });
            }

            protected function nonFactoryHelperMethod()
            {
            }

            public function methodReturningBuiltInType(): string
            {
                return 'not a service';
            }

            public function methodWithoutReturnType()
            {
            }
        };
    }
}
