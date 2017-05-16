<?php

declare(strict_types=1);

use Prooph\Workshop\Infrastructure;
use Prooph\Workshop\Model\Command;
use Prooph\Workshop\Model\Event;

/**
 * All dependencies are set up by simple callable factories.
 *
 * The factories are stored in the map and can be invoked by dependent factories.
 */
$factories = [];

/**
 * Postgres PDO connection used by event store
 *
 * @return PDO
 */
$factories['pdoConnection'] = function (): PDO {
    static $connection;

    if(!$connection) {
        $connection = new PDO(getenv('PDO_DSN'), getenv('PDO_USER'), getenv('PDO_PWD'));
    }
    return $connection;
};

/**
 * MongoDB connection used by projections, snapshots and read model
 *
 * @return Infrastructure\MongoDb\MongoConnection
 */
$factories['mongoConnection'] = function (): Infrastructure\MongoDb\MongoConnection {
    static $mongoConnection;

    if(!$mongoConnection) {
        $client = new \MongoDB\Client(getenv('MONGO_SERVER'));
        $mongoConnection = new Infrastructure\MongoDb\MongoConnection($client, getenv('MONGO_DB_NAME'));
    }
    return $mongoConnection;
};

/**
 * Message factory that uses a simple convention to map message names to FQCNs
 *
 * Internally the message factory delegates message creation to the prooph FQCNMessageFactory
 *
 * @return \Prooph\Common\Messaging\MessageFactory
 */
$factories['messageFactory'] = function () use(&$factories): \Prooph\Common\Messaging\MessageFactory {
    static $messageFactory;

    if(!$messageFactory) {
        $messageFactory = new class() implements \Prooph\Common\Messaging\MessageFactory {
            private $fqcnMessageFactory;

            public function __construct()
            {
                $this->fqcnMessageFactory = new \Prooph\Common\Messaging\FQCNMessageFactory();;
            }

            public function createMessageFromArray(string $messageName, array $messageData): \Prooph\Common\Messaging\Message
            {
                $fqcn = Infrastructure\Util\MessageName::toFQCN($messageName);

                return $this->fqcnMessageFactory->createMessageFromArray($fqcn, $messageData);
            }
        };
    }

    return $messageFactory;
};

$factories['messageConverter'] = function () use(&$factories) {
    static $messageConverter;

    if(!$messageConverter) {
        $messageConverter = new class() implements \Prooph\Common\Messaging\MessageConverter {

            public function convertToArray(\Prooph\Common\Messaging\Message $domainMessage): array
            {
                if($domainMessage instanceof \Prooph\Common\Messaging\DomainMessage) {
                    $messageData = $domainMessage->toArray();
                    $messageData['message_name'] = Infrastructure\Util\MessageName::toMessageName(get_class($domainMessage));
                    return $messageData;
                } else {
                    throw new \RuntimeException("Message must be an instanceof DomainMessage. Got " . get_class($domainMessage));
                }
            }
        };
    }

    return $messageConverter;
};

$factories['amqpMessageProducer'] = function () use(&$factories): \Prooph\ServiceBus\Message\HumusAmqp\AmqpMessageProducer {
    $connection = new \Humus\Amqp\Driver\AmqpExtension\Connection([
        'host' => 'rabbit',
        'port' => 5672,
        'login' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'persistent' => true,
        'read_timeout' => 1, //sec, float allowed
        'write_timeout' => 1, //sec, float allowed,
        'heartbeat' => 0,
    ]);

    $connection->connect();

    $channel = $connection->newChannel();

    $exchange = $channel->newExchange();

    $exchange->setName('workshop-exchange');

    $exchange->setType('fanout');

    $humusProducer = new \Humus\Amqp\JsonProducer($exchange);

    $messageProducer = new \Prooph\ServiceBus\Message\HumusAmqp\AmqpMessageProducer(
        $humusProducer,
        $factories['messageConverter']()
    );

    return $messageProducer;
};

/**
 * Event store configured with a SingleStreamStrategy (we store all events in "event_stream")
 *
 * @return \Prooph\EventStore\EventStore
 */
$factories['eventStore'] = function () use (&$factories): \Prooph\EventStore\EventStore {
    static $eventStore = null;
    if (null === $eventStore) {
        $eventStore = new \Prooph\EventStore\TransactionalActionEventEmitterEventStore(
            new \Prooph\EventStore\Pdo\PostgresEventStore(
                $factories['messageFactory'](),
                $factories['pdoConnection'](),
                new \Prooph\EventStore\Pdo\PersistenceStrategy\PostgresSingleStreamStrategy()
            ),
            new \Prooph\Common\Event\ProophActionEventEmitter(
                \Prooph\EventStore\TransactionalActionEventEmitterEventStore::ALL_EVENTS
            )
        );

        //Publish events after event store commit
        (new \Prooph\EventStoreBusBridge\EventPublisher(
            $factories['eventBus']()
        ))->attachToEventStore($eventStore);
    }
    return $eventStore;
};

/**
 * Aggregate snapshot store
 *
 * @return \Prooph\SnapshotStore\SnapshotStore
 */
$factories['snapshotStore'] = function () use (&$factories): \Prooph\SnapshotStore\SnapshotStore {
    $mongoConnection = $factories['mongoConnection']();
    /** @var Infrastructure\MongoDb\MongoConnection $mongoConnection */
    return new Prooph\SnapshotStore\MongoDb\MongoDbSnapshotStore($mongoConnection->client(), $mongoConnection->dbName());
};

$factories['userRepository'] = function () use(&$factories): \Prooph\Workshop\Model\User\UserRepository {
    static $userRepository;

    if(!$userRepository) {
        $userRepository = new Infrastructure\WriteModel\ProophUserRepository(
            $factories['eventStore'](),
            \Prooph\EventSourcing\Aggregate\AggregateType::fromAggregateRootClass(\Prooph\Workshop\Model\User::class),
            new \Prooph\EventSourcing\EventStoreIntegration\AggregateTranslator(),
            $factories['snapshotStore'](),
            new \Prooph\EventStore\StreamName('event_stream')
        );
    }

    return $userRepository;
};

$factories['configRepository'] = function () use(&$factories): \Prooph\Workshop\Model\Configuration\ConfigurationRepository {
    static $repository;

    if(!$repository) {
        $repository = new Infrastructure\WriteModel\ProophConfigurationRepository(
            $factories['eventStore'](),
            \Prooph\EventSourcing\Aggregate\AggregateType::fromAggregateRootClass(\Prooph\Workshop\Model\Configuration::class),
            new \Prooph\EventSourcing\EventStoreIntegration\AggregateTranslator(),
            $factories['snapshotStore'](),
            new \Prooph\EventStore\StreamName('event_stream')
        );
    }

    return $repository;
};

/**
 * Map of message FQCN to handler factory
 *
 * In case of events FQCN can be mapped to an array of handler factories
 */
$factories['messageHandler'] = [
    Command\RegisterUser::class => function() use(&$factories) {
        return new Command\RegisterUserHandler($factories['userRepository']());
    },
    Command\ChangeUsername::class => function() use(&$factories) {
        return new Command\ChangeUsernameHandler($factories['userRepository']());
    },
    Command\StartNewConfig::class => function() use(&$factories) {
        return new Command\StartNewConfigHandler(
            $factories['userRepository'](),
            $factories['configRepository']()
        );
    },
    Event\UserWasRegistered::class => function() use(&$factories) {
        return [
            new \Prooph\Workshop\ReadModel\UserProjector($factories['mongoConnection']()),
            new \Prooph\Workshop\Model\ProcessManager\UserConfigurationStarter(
                $factories['commandBus'](),
                $factories['messageFactory']()
            ),
            $factories['amqpMessageProducer']()
        ];
    },
    Event\UsernameWasChanged::class => function() use(&$factories) {
        return new \Prooph\Workshop\ReadModel\UserProjector($factories['mongoConnection']());
    },
];

/**
 * Application command bus set up with a custom message router that makes use of the message map
 *
 * @return \Prooph\ServiceBus\CommandBus
 */
$factories['commandBus'] = function () use(&$factories): \Prooph\ServiceBus\CommandBus {
    static $commandBus;

    if(!$commandBus) {
        $commandBus = new \Prooph\ServiceBus\CommandBus();

        //Each command is wrapped in a transaction
        (new \Prooph\EventStoreBusBridge\TransactionManager(
            $factories['eventStore']()
        ))->attachToMessageBus($commandBus);

        $commandBus->attach(
            \Prooph\ServiceBus\MessageBus::EVENT_DISPATCH,
            function(\Prooph\Common\Event\ActionEvent $dispatchEvent) use(&$factories): void {
                $messageName = $dispatchEvent->getParam(\Prooph\ServiceBus\MessageBus::EVENT_PARAM_MESSAGE_NAME);

                $fqcn = Infrastructure\Util\MessageName::toFQCN($messageName);

                if(!isset($factories['messageHandler'][$fqcn])) {
                    throw new \RuntimeException('No handler defined for message: ' . $messageName);
                }

                $handler = $factories['messageHandler'][$fqcn]();

                $dispatchEvent->setParam(\Prooph\ServiceBus\MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $handler);
            },
            \Prooph\ServiceBus\MessageBus::PRIORITY_ROUTE
        );
    }

    return $commandBus;
};

/**
 * Application event bus set up with a custom event router that makes use of the message map
 *
 * @return \Prooph\ServiceBus\EventBus
 */
$factories['eventBus'] = function () use(&$factories): \Prooph\ServiceBus\EventBus {
    static $eventBus;

    if(!$eventBus) {
        $eventBus = new \Prooph\ServiceBus\EventBus();
        $eventBus->attach(
            \Prooph\ServiceBus\MessageBus::EVENT_DISPATCH,
            function(\Prooph\Common\Event\ActionEvent $dispatchEvent) use(&$factories): void {
                $messageName = $dispatchEvent->getParam(\Prooph\ServiceBus\MessageBus::EVENT_PARAM_MESSAGE_NAME);

                $fqcn = Infrastructure\Util\MessageName::toFQCN($messageName);

                if(!isset($factories['messageHandler'][$fqcn])) {
                    $factories['logger']()->debug('No event listeners found for ' . $fqcn);
                    return;
                }

                $listeners = $factories['messageHandler'][$fqcn]();

                if(!is_array($listeners)) {
                    $listeners = [$listeners];
                }

                $dispatchEvent->setParam(\Prooph\ServiceBus\EventBus::EVENT_PARAM_EVENT_LISTENERS, $listeners);
            },
            \Prooph\ServiceBus\MessageBus::PRIORITY_ROUTE
        );
    }

    return $eventBus;
};

/**
 * Map of action middlewares
 *
 * @see app/router.php for routing
 * @see public/index.php for invokation of middlewares
 */
$factories['http'] = [
    \Prooph\Workshop\Http\Home::class => function() use (&$factories): \Prooph\Workshop\Http\Home {
        return new \Prooph\Workshop\Http\Home();
    },
    \Prooph\Workshop\Http\MessageBox::class => function() use (&$factories): \Prooph\Workshop\Http\MessageBox {
        return new \Prooph\Workshop\Http\MessageBox(
            $factories['commandBus'](),
            $factories['eventBus'](),
            $factories['messageFactory']()
        );
    }
];

/**
 * PSR Logger
 *
 * @return \Psr\Log\LoggerInterface
 */
$factories['logger'] = function () use (&$factories): \Psr\Log\LoggerInterface {
    static $logger;

    if(!$logger) {
        $streamHandler = new \Monolog\Handler\StreamHandler('php://stderr');

        $logger = new \Monolog\Logger([$streamHandler]);
    }

    return $logger;
};

return $factories;
