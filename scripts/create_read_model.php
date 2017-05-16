<?php
declare(strict_types = 1);

namespace Prooph\Workshop;

use Prooph\EventStore\EventStore;
use Prooph\EventStore\StreamName;
use Prooph\ServiceBus\EventBus;

chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

$factories = require 'app/factories.php';

/** @var EventStore $eventStore */
$eventStore = $factories['eventStore']();

/** @var EventBus $eventBus */
$eventBus = $factories['eventBus']();

$events = $eventStore->load(new StreamName('event_stream'));

foreach ($events as $event) {
    $eventBus->dispatch($event);
}

echo "done";
exit();
