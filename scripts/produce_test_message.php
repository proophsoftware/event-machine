<?php
declare(strict_types = 1);

namespace Prooph\Workshop;

use Prooph\ServiceBus\Message\HumusAmqp\AmqpMessageProducer;
use Prooph\Workshop\Model\Event\UsernameWasChanged;

chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

$factories = require 'app/factories.php';

/** @var AmqpMessageProducer $amqpMessageProducer */
$amqpMessageProducer = $factories['amqpMessageProducer']();

$amqpMessageProducer->__invoke(UsernameWasChanged::occur('38ac5109-a238-4885-b849-c8d1a92f1a9b', [
    'oldName' => 'test',
    'newName' => 'it works'
]));

echo "done";
exit();