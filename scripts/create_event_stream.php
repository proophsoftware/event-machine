<?php
declare(strict_types=1);

namespace Prooph\Workshop;

use ArrayIterator;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;

chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

$factories = require 'app/factories.php';

/** @var EventStore $eventStore */
$eventStore = $factories['eventStore']();

$eventStore->create(new Stream(new StreamName('event_stream'), new ArrayIterator()));

echo 'done.';